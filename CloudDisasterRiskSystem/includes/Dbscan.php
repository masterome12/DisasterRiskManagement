<?php
require_once __DIR__ . '/functions.php';

/**
 * AI FEATURE — FR-19: Geospatial Hotspot / Anomaly Detection.
 *
 * A dependency-free DBSCAN (Density-Based Spatial Clustering of Applications
 * with Noise) implementation operating on lat/lng incident points. Points
 * are grouped by great-circle proximity (Haversine distance in meters); any
 * point that cannot form or join a dense-enough neighborhood is labeled
 * noise (-1) and excluded from hotspots, per NFR-14 (reproducible results
 * for the same input/parameters — this implementation is fully deterministic,
 * no randomness).
 *
 * Complexity is O(n^2), which is acceptable for the batch/scheduled-job
 * use described in FR-19's main flow at municipal-district data volumes.
 */
class Dbscan
{
    private float $epsMeters;
    private int $minPoints;

    public function __construct(float $epsMeters, int $minPoints)
    {
        $this->epsMeters = $epsMeters;
        $this->minPoints = $minPoints;
    }

    /**
     * @param array $points array of ['id'=>, 'lat'=>, 'lng'=>, ...extra fields kept as-is]
     * @return array{labels: array<int,int>, clusters: array<int, array>} labels[i] = cluster id or -1 for noise
     */
    public function run(array $points): array
    {
        $n = count($points);
        $labels = array_fill(0, $n, 0); // 0 = unvisited, -1 = noise, >0 = cluster id
        $visited = array_fill(0, $n, false);
        $clusterId = 0;

        // Precompute neighbor lists once (O(n^2), fine for batch runs).
        $neighborCache = [];
        $neighbors = function (int $idx) use ($points, $n, &$neighborCache): array {
            if (isset($neighborCache[$idx])) {
                return $neighborCache[$idx];
            }
            $result = [];
            for ($j = 0; $j < $n; $j++) {
                if ($j === $idx) continue;
                $d = haversine_meters(
                    $points[$idx]['lat'], $points[$idx]['lng'],
                    $points[$j]['lat'], $points[$j]['lng']
                );
                if ($d <= $this->epsMeters) {
                    $result[] = $j;
                }
            }
            return $neighborCache[$idx] = $result;
        };

        for ($i = 0; $i < $n; $i++) {
            if ($visited[$i]) continue;
            $visited[$i] = true;

            $neigh = $neighbors($i);
            if (count($neigh) + 1 < $this->minPoints) {
                $labels[$i] = -1; // provisional noise; may be claimed by a later cluster
                continue;
            }

            $clusterId++;
            $labels[$i] = $clusterId;

            // Expand cluster (seed-set growth).
            $seedSet = $neigh;
            for ($k = 0; $k < count($seedSet); $k++) {
                $j = $seedSet[$k];
                if (!$visited[$j]) {
                    $visited[$j] = true;
                    $jNeigh = $neighbors($j);
                    if (count($jNeigh) + 1 >= $this->minPoints) {
                        // merge new neighbors into the seed set
                        foreach ($jNeigh as $nb) {
                            if (!in_array($nb, $seedSet, true)) {
                                $seedSet[] = $nb;
                            }
                        }
                    }
                }
                if ($labels[$j] <= 0) {
                    $labels[$j] = $clusterId;
                }
            }
        }

        // Group points by cluster id, computing a centroid + bounding info per cluster.
        $clusters = [];
        foreach ($labels as $idx => $cid) {
            if ($cid <= 0) continue;
            $clusters[$cid]['points'][] = $points[$idx];
        }
        foreach ($clusters as $cid => &$c) {
            $lats = array_column($c['points'], 'lat');
            $lngs = array_column($c['points'], 'lng');
            $c['id'] = $cid;
            $c['count'] = count($c['points']);
            $c['centroid_lat'] = array_sum($lats) / count($lats);
            $c['centroid_lng'] = array_sum($lngs) / count($lngs);
            // Approx radius = max distance from centroid to a member point.
            $maxDist = 0.0;
            foreach ($c['points'] as $p) {
                $d = haversine_meters($c['centroid_lat'], $c['centroid_lng'], $p['lat'], $p['lng']);
                if ($d > $maxDist) $maxDist = $d;
            }
            $c['radius_meters'] = round($maxDist, 1);
        }
        unset($c);

        return ['labels' => $labels, 'clusters' => array_values($clusters)];
    }
}
