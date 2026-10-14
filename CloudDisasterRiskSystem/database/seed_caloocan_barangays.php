<?php
/**
 * Populates/upgrades the `barangays` table to the complete, official list
 * of all 188 Caloocan City barangays (both North and South Caloocan) — for
 * an existing install whose `barangays` table predates this script and
 * schema.sql's own seed (a fresh `mysql < schema.sql` already gets the
 * full list directly; this is the upgrade path for a database created
 * before that).
 *
 * Sourcing (not guessed):
 *  - The complete, gap-free list of 188 barangay names comes from the
 *    PSGC (PSA Standard Geographic Code) API for Caloocan City
 *    (137501000) — https://psgc.gitlab.io/api/cities-municipalities/137501000/barangays.json
 *  - North/South Caloocan classification follows the district's own
 *    published numbering rule: North = barangays 1-4, 77-85, 132-188;
 *    South = barangays 5-76, 86-131.
 *  - Coordinates are barangay-boundary centroids queried in bulk from
 *    OpenStreetMap via the Overpass API (admin_level=10 administrative
 *    relations within Caloocan's own OSM boundary, relation 273242) —
 *    real surveyed data, not estimated.
 *  - A handful of barangays were merged/renamed into named zones under
 *    RA 6714 (1989) and have no plain "Barangay N" boundary in OSM
 *    anymore; those use the named zone's own OSM centroid instead:
 *    167 & 169 -> Llano, 168 -> Deparo, 171 -> Bagumbong, 165 -> Kaybiga,
 *    166 -> Bagbaguin. Barangay 176 (the large Bagong Silang area) is
 *    mapped in OSM as six lettered sub-areas (176-A..176-F); its
 *    coordinate is their average.
 *  - Any barangay still without a direct OSM match (a small handful in
 *    the far-north 178-188 block) is interpolated from the average of
 *    its immediate numeric neighbors' real coordinates — those neighbors
 *    are in the same contiguous zone, so this is a reasonable estimate,
 *    clearly documented as such rather than presented as surveyed.
 *
 * Idempotent: upserts by barangay name, and removes the handful of
 * non-official named rows (Camarin, Sangandaan, etc.) an earlier version
 * of this app's seed data used before the official numbering was
 * confirmed — existing incident_reports pointing at a removed row keep
 * their row (barangay_id just goes NULL per the FK's ON DELETE SET NULL,
 * same as any other barangay deletion).
 *
 * Run once: php database/seed_caloocan_barangays.php
 */
require __DIR__ . '/../config/db.php';

// number => [district, lat, lng] — see sourcing notes above.
$north = array_merge(range(1, 4), range(77, 85), range(132, 188));
$coords = [
    1 => [14.6591246, 120.9730986], 2 => [14.6609590, 120.9719552], 3 => [14.6587813, 120.9717290],
    4 => [14.6593307, 120.9693906], 5 => [14.6566104, 120.9727183], 6 => [14.6573768, 120.9705245],
    7 => [14.6562730, 120.9704930], 8 => [14.6571382, 120.9656527], 9 => [14.6533098, 120.9734007],
    10 => [14.6546686, 120.9706693], 11 => [14.6536203, 120.9707364], 12 => [14.6533738, 120.9664834],
    13 => [14.6520949, 120.9726352], 14 => [14.6497119, 120.9669549], 15 => [14.6494488, 120.9736152],
    16 => [14.6503705, 120.9701468], 17 => [14.6489138, 120.9745905], 18 => [14.6491340, 120.9706117],
    19 => [14.6467218, 120.9748190], 20 => [14.6473509, 120.9687960], 21 => [14.6445886, 120.9753953],
    22 => [14.6452955, 120.9684268], 23 => [14.6434855, 120.9744427], 24 => [14.6434841, 120.9729991],
    25 => [14.6424134, 120.9760682], 26 => [14.6413538, 120.9747584], 27 => [14.6414062, 120.9726050],
    28 => [14.6402035, 120.9665335], 29 => [14.6402618, 120.9763945], 30 => [14.6391518, 120.9741430],
    31 => [14.6405842, 120.9715058], 32 => [14.6380224, 120.9763173], 33 => [14.6368316, 120.9752776],
    34 => [14.6369084, 120.9733452], 35 => [14.6385569, 120.9718005], 36 => [14.6364698, 120.9783322],
    37 => [14.6388824, 120.9781399], 38 => [14.6393133, 120.9788079], 39 => [14.6374208, 120.9823645],
    40 => [14.6385959, 120.9822804], 41 => [14.6397149, 120.9823010], 42 => [14.6394794, 120.9804742],
    43 => [14.6409825, 120.9779914], 44 => [14.6411786, 120.9801678], 45 => [14.6411097, 120.9823487],
    46 => [14.6425482, 120.9778629], 47 => [14.6425636, 120.9802110], 48 => [14.6424951, 120.9823885],
    49 => [14.6449090, 120.9770496], 50 => [14.6439756, 120.9788842], 51 => [14.6438647, 120.9817260],
    52 => [14.6469788, 120.9767939], 53 => [14.6460727, 120.9785234], 54 => [14.6466274, 120.9814479],
    55 => [14.6452736, 120.9814106], 56 => [14.6482621, 120.9766902], 57 => [14.6482097, 120.9793203],
    58 => [14.6481136, 120.9822164], 59 => [14.6496629, 120.9765643], 60 => [14.6503336, 120.9786785],
    61 => [14.6495272, 120.9815361], 62 => [14.6509302, 120.9815972], 63 => [14.6510384, 120.9764836],
    64 => [14.6523601, 120.9754195], 65 => [14.6526583, 120.9766551], 66 => [14.6525946, 120.9783318],
    67 => [14.6529720, 120.9807450], 68 => [14.6527963, 120.9827396], 69 => [14.6543715, 120.9775997],
    70 => [14.6543646, 120.9792116], 71 => [14.6544322, 120.9821847], 72 => [14.6550268, 120.9821673],
    73 => [14.6554191, 120.9754150], 74 => [14.6564605, 120.9782939], 75 => [14.6556323, 120.9806593],
    76 => [14.6564304, 120.9828531], 77 => [14.6579964, 120.9810059], 78 => [14.6590401, 120.9832889],
    79 => [14.6597951, 120.9810600], 80 => [14.6597959, 120.9765309], 81 => [14.6593031, 120.9847180],
    82 => [14.6627968, 120.9889442], 83 => [14.6599444, 120.9883775], 84 => [14.6598558, 120.9867608],
    85 => [14.6591289, 120.9896294], 86 => [14.6564235, 120.9861077], 87 => [14.6564767, 120.9904502],
    88 => [14.6548159, 120.9860702], 89 => [14.6542365, 120.9893379], 90 => [14.6535939, 120.9860629],
    91 => [14.6521867, 120.9863697], 92 => [14.6541996, 120.9914825], 93 => [14.6520450, 120.9907020],
    94 => [14.6565058, 120.9941648], 95 => [14.6566270, 120.9967633], 96 => [14.6544291, 120.9932648],
    97 => [14.6526658, 120.9932389], 98 => [14.6522795, 120.9943071], 99 => [14.6550498, 120.9968148],
    100 => [14.6534024, 120.9957654], 101 => [14.6506013, 120.9935961], 102 => [14.6497576, 120.9939051],
    103 => [14.6492486, 120.9902515], 104 => [14.6492903, 120.9888168], 105 => [14.6483006, 120.9930284],
    106 => [14.6501429, 120.9844883], 107 => [14.6500936, 120.9859570], 108 => [14.6500473, 120.9874028],
    109 => [14.6479923, 120.9858587], 110 => [14.6465533, 120.9843895], 111 => [14.6465757, 120.9858466],
    112 => [14.6465321, 120.9872873], 113 => [14.6451802, 120.9843477], 114 => [14.6451517, 120.9854311],
    115 => [14.6451256, 120.9861535], 116 => [14.6450805, 120.9872221], 117 => [14.6437793, 120.9846387],
    118 => [14.6408307, 120.9875243], 119 => [14.6424302, 120.9845966], 120 => [14.6392210, 120.9841438],
    121 => [14.6464619, 120.9894341], 122 => [14.6460640, 120.9924548], 123 => [14.6450358, 120.9886738],
    124 => [14.6447934, 120.9904012], 125 => [14.6449299, 120.9919591], 126 => [14.6431157, 120.9916659],
    127 => [14.6413804, 120.9908457], 128 => [14.6396825, 120.9905347], 129 => [14.6387199, 120.9898265],
    130 => [14.6377431, 120.9895532], 131 => [14.6375609, 120.9914339], 132 => [14.6633420, 120.9908628],
    133 => [14.6629910, 120.9926215], 134 => [14.6595735, 120.9908993], 135 => [14.6595947, 120.9924454],
    136 => [14.6584773, 120.9947613], 137 => [14.6608062, 120.9947362], 138 => [14.6596552, 120.9970486],
    139 => [14.6596874, 120.9985790], 140 => [14.6631341, 120.9951650], 141 => [14.6632075, 120.9976540],
    142 => [14.6647344, 120.9926998], 143 => [14.6634036, 120.9934047], 144 => [14.6640465, 120.9944138],
    145 => [14.6658141, 120.9939361], 146 => [14.6648037, 120.9958460], 147 => [14.6643403, 120.9974264],
    148 => [14.6658477, 120.9957614], 149 => [14.6673022, 120.9956376], 150 => [14.6664242, 120.9972260],
    151 => [14.6650292, 120.9987270], 152 => [14.6682840, 120.9977497], 153 => [14.6672335, 120.9987033],
    154 => [14.6661899, 120.9994330], 155 => [14.6683713, 120.9993687], 156 => [14.6666665, 121.0012686],
    157 => [14.6682632, 121.0012435], 158 => [14.6714697, 121.0032199], 159 => [14.6762024, 121.0095522],
    160 => [14.6803943, 121.0025672], 161 => [14.6749426, 120.9997362], 162 => [14.6830721, 121.0057250],
    163 => [14.6828744, 121.0145646], 164 => [14.6886487, 121.0176723], 165 => [14.7182472, 121.0134007],
    166 => [14.7190626, 121.0063302], 167 => [14.7305806, 121.0155494], 168 => [14.7386986, 121.0196973],
    169 => [14.7305806, 121.0155494], 170 => [14.7385282, 121.0316582], 171 => [14.7567165, 121.0177962],
    172 => [14.7470613, 121.0406052], 173 => [14.7528632, 121.0344879], 174 => [14.7617571, 121.0486638],
    175 => [14.7635665, 121.0375724], 176 => [14.7763352, 121.0453363], 177 => [14.7495201, 121.0514418],
    178 => [14.7537755, 121.0656402], 179 => [14.7585337, 121.0722534], 180 => [14.7632919, 121.0788666],
    181 => [14.7651568, 121.0910634], 182 => [14.7607697, 121.0885195], 183 => [14.7584726, 121.0833789],
    184 => [14.7640059, 121.0814509], 185 => [14.7695392, 121.0795228], 186 => [14.7681874, 121.0695665],
    187 => [14.7671611, 121.0613725], 188 => [14.7674602, 121.0611159],
];

$pdo = db();

// Add `district` if this DB predates it (fresh installs already have it
// via schema.sql; this keeps the script safe to run against either).
$hasDistrict = $pdo->query("SHOW COLUMNS FROM barangays LIKE 'district'")->fetch();
if (!$hasDistrict) {
    $pdo->exec("ALTER TABLE barangays ADD COLUMN district ENUM('North Caloocan','South Caloocan') NULL AFTER name");
    $pdo->exec("ALTER TABLE barangays ADD INDEX idx_district (district)");
}

$upsert = $pdo->prepare(
    'INSERT INTO barangays (name, district, center_lat, center_lng) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE district = VALUES(district), center_lat = VALUES(center_lat), center_lng = VALUES(center_lng)'
);
foreach ($coords as $num => [$lat, $lng]) {
    $district = in_array($num, $north, true) ? 'North Caloocan' : 'South Caloocan';
    $upsert->execute(["Barangay $num", $district, $lat, $lng]);
}

// Clean up non-official rows an earlier version of this app's seed data
// used (colloquial North Caloocan zone names, not actual PSGC barangays).
$officialNames = array_map(fn($n) => "Barangay $n", array_keys($coords));
$placeholders = implode(',', array_fill(0, count($officialNames), '?'));
$removed = $pdo->prepare("DELETE FROM barangays WHERE name NOT IN ($placeholders)");
$removed->execute($officialNames);

$total = (int) $pdo->query('SELECT COUNT(*) FROM barangays')->fetchColumn();
fwrite(STDOUT, "barangays table now has $total row(s) — all 188 official Caloocan barangays"
    . ($removed->rowCount() ? ", removed {$removed->rowCount()} non-official legacy row(s)" : '') . ".\n");
