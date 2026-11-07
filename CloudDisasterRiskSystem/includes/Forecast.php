<?php
require_once __DIR__ . '/functions.php';

/**
 * AI FEATURE (Phase 2) — FR-20: Predictive Risk Modeling.
 *
 * Per the SRS (Section 7.2) and NFR-15, this forecast layer must never be
 * exposed as a validated prediction until it has been backtested against
 * held-out historical data and meets an accuracy threshold. This class
 * implements that gate explicitly: build() always runs a backtest first,
 * and callers must check accepted() before treating the forecast as
 * production-ready. The technique is a simple ordinary-least-squares
 * linear trend over monthly incident counts — intentionally lightweight
 * and auditable rather than a black box, consistent with "clearly labeled
 * as a forecast, not a confirmed hazard" (FR-20 main flow, step 4).
 */
class Forecast
{
    /**
     * @param array<int,int> $monthlyCounts counts indexed 0..n-1, chronological (oldest first)
     */
    public static function linearRegression(array $monthlyCounts): array
    {
        $n = count($monthlyCounts);
        $xs = range(0, $n - 1);
        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($monthlyCounts) / $n;

        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += ($xs[$i] - $meanX) * ($monthlyCounts[$i] - $meanY);
            $den += ($xs[$i] - $meanX) ** 2;
        }
        $slope = $den != 0 ? $num / $den : 0.0;
        $intercept = $meanY - $slope * $meanX;

        return ['slope' => $slope, 'intercept' => $intercept];
    }

    private static function predict(array $model, int $x): float
    {
        return $model['slope'] * $x + $model['intercept'];
    }

    /**
     * Backtests the model: trains on all but the last $holdout periods,
     * predicts those held-out periods, and returns both the Mean Absolute
     * Error and a period-by-period actual-vs-predicted breakdown — the
     * breakdown is what lets the UI show *why* a backtest passed or failed,
     * rather than just a single opaque number.
     *
     * @return array{mae: float|null, detail: array}
     */
    public static function backtest(array $monthlyCounts, array $labels = [], int $holdout = 3): array
    {
        $n = count($monthlyCounts);
        if ($n <= $holdout + 2) {
            return ['mae' => null, 'detail' => []]; // not enough history to backtest meaningfully
        }
        $train = array_slice($monthlyCounts, 0, $n - $holdout);
        $actualHoldout = array_slice($monthlyCounts, $n - $holdout);
        $labelHoldout = array_slice($labels, $n - $holdout);

        $model = self::linearRegression($train);
        $errors = [];
        $detail = [];
        foreach ($actualHoldout as $i => $actual) {
            $predicted = self::predict($model, count($train) + $i);
            $err = abs($predicted - $actual);
            $errors[] = $err;
            $detail[] = [
                'period'    => $labelHoldout[$i] ?? ('holdout ' . ($i + 1)),
                'actual'    => $actual,
                'predicted' => round(max(0, $predicted), 1),
                'error'     => round($err, 1),
            ];
        }
        return ['mae' => array_sum($errors) / count($errors), 'detail' => $detail];
    }

    /** Back-compat: MAE only, no detail breakdown. */
    public static function backtestMae(array $monthlyCounts, int $holdout = 3): ?float
    {
        return self::backtest($monthlyCounts, [], $holdout)['mae'];
    }

    /**
     * Full pipeline: train on full history, backtest, forecast next $horizon
     * months, and report whether NFR-15's threshold is met.
     *
     * @return array{trained_on:int, mae: float|null, accepted: bool, threshold: float, forecast: array}
     */
    public static function build(array $monthlyCounts, array $monthLabels, int $horizon = 3,
                                   float $threshold = FORECAST_BACKTEST_THRESHOLD_MAE): array
    {
        $n = count($monthlyCounts);
        $bt = self::backtest($monthlyCounts, $monthLabels);
        $mae = $bt['mae'];
        $accepted = $mae !== null && $mae <= $threshold;

        $model = self::linearRegression($monthlyCounts);
        $forecast = [];
        for ($h = 1; $h <= $horizon; $h++) {
            $value = max(0, self::predict($model, $n - 1 + $h));
            $forecast[] = [
                'period' => 'T+' . $h,
                'predicted_incidents' => round($value, 1),
            ];
        }

        // Descriptive stats over the observed history, so the UI can show
        // more than just the raw series — average load, the extremes, and a
        // plain first-vs-last trend percentage alongside the regression.
        $avg = round(array_sum($monthlyCounts) / $n, 2);
        $min = min($monthlyCounts);
        $max = max($monthlyCounts);
        $first = $monthlyCounts[0];
        $last = $monthlyCounts[$n - 1];
        $trendPct = $first != 0 ? round((($last - $first) / $first) * 100, 1) : null;

        return [
            'trained_on_months' => $n,
            'month_labels'      => $monthLabels,
            'observed'          => $monthlyCounts,
            'stats' => [
                'average' => $avg,
                'min' => $min,
                'max' => $max,
                'first_observed' => $first,
                'last_observed' => $last,
                'trend_pct_change' => $trendPct, // null when first period was 0 (division undefined)
                'slope_per_period' => round($model['slope'], 3),
            ],
            'backtest_mae'      => $mae !== null ? round($mae, 3) : null,
            'backtest_detail'   => $bt['detail'], // actual vs predicted for each held-out period
            'threshold_mae'     => $threshold,
            'accepted'          => $accepted, // NFR-15 gate — only true if backtest passed
            'forecast'          => $accepted ? $forecast : [], // withheld from "production" if not accepted
            'note' => $accepted
                ? 'Backtest passed threshold; forecast is provisional and must be read as a trend estimate, not a confirmed hazard.'
                : 'Backtest did not meet the accuracy threshold (NFR-15). Forecast withheld pending more data / tuning.',
        ];
    }
}
