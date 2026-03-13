<?php
/**
 * generate_opc_instance.php - Generator for One-Per-Category Partitioning instances
 * PHP 7.4.9+ | Procedural | Outputs unified JSON structure
 * 
 * OVERVIEW:
 * This script generates benchmark instances for the One-Per-Category Partitioning (OPC) problem.
 * Given C categories with M items each, the goal is to select exactly one item per category
 * and partition the C selected items into subsets with balanced sums.
 * 
 * GENERATION:
 * - Each distribution type produces values with different statistical properties
 * - Categories are unsorted by default (set $SORT_CATEGORIES = true to enable rsort)
 * - Same seed + parameters = reproducible output
 * 
 * OUTPUT FORMAT:
 * {
 *   "author": { "id": "...", "orcid": "..." },
 *   "metadata": {
 *     "problem_type": "One-Per-Category Partitioning",
 *     "directory": "...",
 *     "structure": { "categories": C, "items_per_category": M, "total_items": C*M },
 *     "values": { "min": X, "max": Y, "total_sum": Z },
 *     "distribution": { "type": "...", "seed": N, "seed_url": "..." }
 *   },
 *   "categories": [
 *     { "id": 1, "sum": S, "items": [v1, v2, ...] },
 *     ...
 *   ]
 * }
 * 
 * USAGE:
 *   CLI: php generate_opc_instance.php --c=10 --m=100 --max=10000 --seed=42 --type=non-uniform --dir="path/to/JSON"
 *   Web: ?c=10&m=100&max=10000&seed=42&type=non-uniform&dir=path/to/JSON
 *
 * CREDIT:
 * - Script authored by [mllmso] and generated with the assistance of an AI system
 */

// === Configuration ===
$SORT_CATEGORIES = false;  // Set to true to enable rsort (descending order)

// === Parameters ===
$options = getopt("", array("c:", "m:", "max:", "seed:", "type:", "dir:", "out:"));

$c     = isset($options['c'])   ? (int)$options['c']   : 10;
$m     = isset($options['m'])   ? (int)$options['m']   : 100;
$max   = isset($options['max']) ? (int)$options['max'] : 10000;
$seed  = isset($options['seed']) ? (int)$options['seed'] : 891780555;
$type  = isset($options['type']) ? $options['type'] : 'non-uniform';
$dir   = isset($options['dir']) ? rtrim($options['dir'], '/\\') : '';
$out   = isset($options['out']) ? $options['out'] : "opc_instance_{$type}.json";

mt_srand($seed);

// === Distribution generators (easy to hard) ===

/**
 * Generate purely random uniform instances (baseline distribution).
 * 
 * HOW IT WORKS:
 * - Each value is independently drawn from Uniform[min, max].
 * - No correlation between categories or items.
 * - Statistical properties: flat density, high entropy, no structure.
 * 
 * WHY USE IT:
 * - Baseline comparison: establishes performance floor for any solver.
 * - Algorithm calibration: tune parameters on predictable, structureless data.
 * - Sanity checks: verify solver correctness without distributional traps.
 * - Reference point: harder distributions should show measurable degradation.
 * 
 * CHALLENGES FOR SOLVERS:
 * - No exploitable patterns → forces general-purpose strategies.
 * - High variance in subset sums → balance requires careful selection.
 * - Large search space (m^c) with no pruning hints.
 * 
 * TYPICAL USE CASES:
 * - Initial solver development and debugging.
 * - Performance benchmarking (report results relative to uniform baseline).
 * - Statistical analysis of solver behavior on random inputs.
 */
function gen_uniform($c, $m, $min, $max, $sort) {
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array();
        for ($j = 0; $j < $m; $j++) $vals[] = mt_rand($min, $max);
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

/**
 * Generate correlated instances with shared base pattern + noise.
 * 
 * HOW IT WORKS:
 * - A single base pattern of M values is generated first.
 * - Each category receives a noisy copy: value = base[j] + noise[-5, +5].
 * - Categories are statistically similar but not identical.
 * 
 * WHY USE IT:
 * - Realistic scenarios: many real-world OPC problems have correlated categories
 *   (e.g., sensor readings, financial assets, resource allocations).
 * - Tests solver robustness to subtle inter-category dependencies.
 * - Creates "local optima" traps: greedy selection on one category may mislead
 *   global balance due to correlated structure.
 * 
 * CHALLENGES FOR SOLVERS:
 * - Apparent patterns may be misleading (noise breaks exact matches).
 * - Solvers that assume independence may underperform.
 * - Requires balancing across similar-but-different value sets.
 * 
 * TYPICAL USE CASES:
 * - Evaluating solvers on semi-structured, realistic data.
 * - Testing adaptation mechanisms that detect and exploit correlations.
 * - Stress-testing balance heuristics under correlated uncertainty.
 */
function gen_correlated($c, $m, $min, $max, $sort) {
    $base = array();
    for ($j = 0; $j < $m; $j++) $base[] = mt_rand($min, $max);
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array();
        foreach ($base as $v) $vals[] = max($min, min($max, $v + mt_rand(-5, 5)));
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

/**
 * Generate "twin trap" instances with paired near-identical values.
 * 
 * HOW IT WORKS:
 * - Values are generated in pairs: (base, base+1) for small random base.
 * - Each category contains ~M/2 such pairs (plus one singleton if M is odd).
 * - Creates many locally optimal choices that are globally suboptimal.
 * 
 * WHY USE IT:
 * - Tests solver ability to escape local optima and see global structure.
 * - Simulates real-world scenarios with near-duplicate options
 *   (e.g., products with similar prices, tasks with similar durations).
 * - Creates combinatorial ambiguity: many selections yield similar partial sums,
 *   but only few achieve global balance.
 * 
 * CHALLENGES FOR SOLVERS:
 * - Greedy or myopic strategies get trapped selecting "obvious" pairs.
 * - Requires lookahead or global reasoning to avoid balance drift.
 * - High sensitivity: swapping one twin for its pair can significantly
 *   improve or degrade final discrepancy.
 * 
 * TYPICAL USE CASES:
 * - Benchmarking metaheuristics (simulated annealing, tabu search, GA).
 * - Evaluating exact solvers on instances with many symmetric solutions.
 * - Testing solver resilience to deceptive local structure.
 */
function gen_twin_trap($c, $m, $min, $max, $sort) {
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array();
        for ($j = 0; $j < (int)($m/2); $j++) {
            $b = mt_rand($min, max($min, $max - 1));
            $vals[] = $b;
            $vals[] = $b + 1;
        }
        if ($m % 2) $vals[] = mt_rand($min, $max);
        $vals = array_slice($vals, 0, $m);
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

/**
 * Generate "dominant outlier" instances with one large value per category.
 * 
 * HOW IT WORKS:
 * - Each category contains one dominant value in [0.7*max, max].
 * - Remaining M-1 values are small, in [min, 0.3*max].
 * - Creates strong selection pressure: pick the outlier or not?
 * 
 * WHY USE IT:
 * - Models real-world decisions with "big vs. many small" tradeoffs
 *   (e.g., one expensive resource vs. several cheap alternatives).
 * - Tests solver ability to handle skewed value distributions.
 * - Creates clear but conflicting signals: outliers dominate sums,
 *   but selecting all outliers may unbalance subsets.
 * 
 * CHALLENGES FOR SOLVERS:
 * - Greedy selection of outliers often leads to highly unbalanced subsets.
 * - Ignoring outliers may miss opportunities for better global balance.
 * - Requires strategic tradeoff analysis: when to pick the dominant item?
 * 
 * TYPICAL USE CASES:
 * - Evaluating solvers on skewed, real-world-like distributions.
 * - Testing decision heuristics that weigh item magnitude vs. balance impact.
 * - Benchmarking exact methods on instances with strong selection asymmetry.
 */
function gen_dominant($c, $m, $min, $max, $sort) {
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array(mt_rand((int)($max * 0.7), $max));
        for ($j = 1; $j < $m; $j++) $vals[] = mt_rand($min, (int)($max * 0.3));
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

/**
 * Generate "complementary" instances with two distinct value clusters.
 * 
 * HOW IT WORKS:
 * - First half of items in each category: values in [0.4*max, 0.6*max] (mid-range).
 * - Second half: values in [0.5*max, 0.7*max] (slightly higher mid-range).
 * - Clusters overlap but have distinct central tendencies.
 * 
 * WHY USE IT:
 * - Simulates scenarios with two "types" of options per category
 *   (e.g., standard vs. premium, local vs. remote, fast vs. slow).
 * - Tests solver ability to mix selections from different clusters
 *   to achieve balance.
 * - Creates subtle tradeoffs: mixing clusters may yield better balance
 *   than sticking to one type.
 * 
 * CHALLENGES FOR SOLVERS:
 * - Simple strategies (always pick low, always pick high) fail to balance.
 * - Requires understanding of cluster interactions across categories.
 * - Optimal solutions often involve heterogeneous selections
 *   (some mid, some high) rather than uniform cluster choice.
 * 
 * TYPICAL USE CASES:
 * - Evaluating solvers on multi-modal value distributions.
 * - Testing adaptive heuristics that learn cluster preferences.
 * - Benchmarking balance strategies under structured heterogeneity.
 */
function gen_complementary($c, $m, $min, $max, $sort) {
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array();
        for ($j = 0; $j < (int)($m/2); $j++) {
            $vals[] = mt_rand((int)($max * 0.4), (int)($max * 0.6));
        }
        for ($j = (int)($m/2); $j < $m; $j++) {
            $vals[] = mt_rand((int)($max * 0.5), (int)($max * 0.7));
        }
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

/**
 * Generate "non-uniform" instances with mixed distribution patterns.
 * 
 * HOW IT WORKS:
 * - Categories cycle through 4 sub-patterns (twin-like, dominant,
 *   tight-cluster, pure-random) to create heterogeneous structure.
 * - Each category has a different statistical profile, but all values
 *   remain in [min, max].
 * - Designed to combine challenges from multiple simpler distributions.
 * 
 * WHY USE IT:
 * - Represents realistic, heterogeneous problem instances where no single
 *   distribution assumption holds globally.
 * - Tests solver generality: can one strategy handle mixed patterns?
 * - Provides a challenging benchmark that rewards adaptive, robust solvers.
 * 
 * CHALLENGES FOR SOLVERS:
 * - No single heuristic dominates: solvers must adapt per-category.
 * - Pattern recognition becomes valuable but non-trivial.
 * - Global balance requires coordinating selections across diverse structures.
 * 
 * TYPICAL USE CASES:
 * - Final benchmarking: report solver performance on non-uniform as
 *   the primary difficulty metric.
 * - Evaluating adaptive or learning-based solvers.
 * - Stress-testing solver robustness under distributional heterogeneity.
 * 
 * NOTE:
 * - This is the recommended default for publication-quality benchmarks.
 * - Use simpler distributions (uniform, correlated) for debugging and
 *   ablation studies.
 */
function gen_non_uniform($c, $m, $min, $max, $sort) {
    // Mixed distribution: combines patterns to create challenging instances
    $cats = array();
    for ($i = 0; $i < $c; $i++) {
        $vals = array();
        switch ($i % 4) {
            case 0: // Twin-like pairs
                for ($j = 0; $j < (int)($m/2); $j++) {
                    $b = mt_rand((int)($max * 0.3), (int)($max * 0.7));
                    $vals[] = $b;
                    $vals[] = $b + mt_rand(1, 3);
                }
                // FIX: Add odd value if m is odd
                if ($m % 2) {
                    $vals[] = mt_rand($min, $max);
                }
                break;
            case 1: // Dominant outlier
                $vals[] = mt_rand((int)($max * 0.8), $max);
                for ($j = 1; $j < $m; $j++) {
                    $vals[] = mt_rand($min, (int)($max * 0.2));
                }
                break;
            case 2: // Tight cluster
                $base = mt_rand((int)($max * 0.4), (int)($max * 0.6));
                for ($j = 0; $j < $m; $j++) {
                    $vals[] = $base + mt_rand(-10, 10);
                }
                break;
            case 3: // Pure random
                for ($j = 0; $j < $m; $j++) {
                    $vals[] = mt_rand($min, $max);
                }
                break;
        }
        // Ensure exactly m items (safety check)
        $vals = array_slice($vals, 0, $m);
        while (count($vals) < $m) {
            $vals[] = mt_rand($min, $max);
        }
        if ($sort) rsort($vals);
        $cats[] = $vals;
    }
    return $cats;
}

// === Select and run generator ===
$generators = array(
    'uniform'       => 'gen_uniform',
    'correlated'    => 'gen_correlated',
    'twin_trap'     => 'gen_twin_trap',
    'dominant'      => 'gen_dominant',
    'complementary' => 'gen_complementary',
    'non-uniform'   => 'gen_non_uniform'
);

$generator = isset($generators[$type]) ? $generators[$type] : 'gen_uniform';
$categories = $generator($c, $m, 1, $max, $SORT_CATEGORIES);

// === Build output structure ===
$all_values = array_merge(...$categories);
$total_sum = array_sum($all_values);

// Format categories with id, sum, items
$formatted = array();
for ($i = 0; $i < $c; $i++) {
    $formatted[] = array(
        'id'    => $i + 1,
        'sum'   => array_sum($categories[$i]),
        'items' => $categories[$i]
    );
}

$output = array(
    'author' => array(
        'id'    => 'mllmso',
        'orcid' => 'https://orcid.org/0009-0005-3698-7366'
    ),
    'metadata' => array(
        'problem_type' => 'One-Per-Category Partitioning',
        'directory'    => $dir,
        'structure'    => array(
            'categories'          => $c,
            'items_per_category'  => $m,
            'total_items'         => $c * $m
        ),
        'values' => array(
            'min'       => min($all_values),
            'max'       => max($all_values),
            'total_sum' => $total_sum
        ),
        'distribution' => array(
            'type'     => $type,
            'seed'     => $seed,
            'seed_url' => 'https://www.random.org/integers/?num=1&min=100000000&max=1000000000&col=1&base=10&format=html&rnd=date.' . date('Y-m-d')
        )
    ),
    'categories' => $formatted
);

// === Save to file ===
$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
file_put_contents($out, $json);

echo "<pre>";
echo "Generated: $out\n";
echo "  Type: $type, Categories: $c, Items/category: $m\n";
echo "  Value range: [" . min($all_values) . ", " . max($all_values) . "], Sum: $total_sum\n";
echo "  Seed: $seed, Sorted: " . ($SORT_CATEGORIES ? 'yes' : 'no') . "\n";
?>