<?php
/**
 * validate_opc_solution.php - Minimal recursive validator for OPC solutions
 * PHP 7.4.9+ | Procedural | Plain text output | Comments in English
 * 
 * HOW VALIDATION WORKS:
 * 
 * 1. INSTANCE FILE (opc_instance_*.json)
 *    - Contains categories: each has "items" (list of values)
 * 
 * 2. SOLUTION FILES (t{N}_solution_values.json)
 *    - Contains subsets: each has "values" (C values, one per category)
 * 
 * 3. VALIDATION LOGIC:
 *    a) Structure: each subset has exactly C values
 *    b) Membership + Uniqueness: values[i] must exist in categories[i].items
 *       → Value is REMOVED after use (consumption model)
 *    c) Sum computation: array_sum(values) per subset
 *    d) Balance: discrepancy = max_sum - min_sum; optimal if <= 1
 * 
 * 4. OUTPUT:
 *    - Relative path, expected values, optimal status, errors if any
 * 
 * 5. INSTALLATION: 
 *	  - Place the (translated) script outside the "Optimal_One_Per_Category_Partitioning" folder and run it.
 * 
 *    OTHER USAGE:
 *      http://localhost/validate_opc_solution.php?root=/path/to/scan
 *
 *    EXPECTED OUTPUT:
 *	  > see validate_opc_solution_expected_output.txt
 * 
 * 6. CREDIT:
 *	  - Script authored by [mllmso] and generated with the assistance of an AI system
 */

$root = $_GET['root'] ?? __DIR__;
$root = str_replace('\\', '/', rtrim($root, '/\\'));

echo "<pre>\n";
echo "===============================================================\n";
echo "  OPC SOLUTION VALIDATOR \n";
echo "===============================================================\n";
echo "Root: $root\n\n";

$checked = 0; $optimal = 0; $errors = 0;

scan($root, $root, $checked, $optimal, $errors);

echo "\n===============================================================\n";
echo "Summary: checked=$checked, optimal=$optimal, errors=$errors\n";
echo "===============================================================\n";
echo "</pre>\n";

function scan($dir, $baseRoot, &$checked, &$optimal, &$errors) {
    $items = @scandir($dir);
    if (!$items) return;
    
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            if (strtoupper($item) == 'JSON') {
                validateFolder($path, $baseRoot, $checked, $optimal, $errors);
            } else {
                scan($path, $baseRoot, $checked, $optimal, $errors);
            }
        }
    }
}

function validateFolder($jsonDir, $baseRoot, &$checked, &$optimal, &$errors) {
    $files = @scandir($jsonDir);
    if (!$files) return;
    
    // Find instance file
    $instance = null;
    foreach ($files as $f) {
        if (strpos($f, 'opc_instance') === 0 && substr($f, -5) == '.json') {
            $instance = json_decode(file_get_contents($jsonDir . '/' . $f), true);
            break;
        }
    }
    if (!$instance || !isset($instance['categories'])) return;
    
    // Extract categories: $cats[catIndex] = [val1, val2, ...]
    $cats = array();
    foreach ($instance['categories'] as $i => $cat) {
        $cats[$i] = isset($cat['items']) ? $cat['items'] : $cat;
    }
    $C = count($cats);
    $M = count($cats[0]);
    
    // Process each solution file
    foreach ($files as $f) {
        if (substr($f, -5) != '.json') continue;
        if (strpos($f, 'opc_instance') === 0) continue;
        if (strpos($f, '_indices') !== false) continue;
        if (!preg_match('/^t\d+.*\.json$/', $f)) continue;
        
        $checked++;
        $solPath = $jsonDir . '/' . $f;
        
        // Relative path for display
        $displayPath = $solPath;
        if (strpos($displayPath, $baseRoot) === 0) {
            $displayPath = substr($displayPath, strlen($baseRoot) + 1);
        }
        
        $sol = json_decode(file_get_contents($solPath), true);
        $subsets = isset($sol['subsets']) ? $sol['subsets'] : array();
        
        // === VALIDATION WITH CONSUMPTION ===
        // Create working copies of categories (so we can remove values)
        $workingCats = array();
        for ($i = 0; $i < $C; $i++) {
            $workingCats[$i] = $cats[$i];  // Copy by value
        }
        
        $sums = array(); $valid = true; $errMsg = '';
        
        foreach ($subsets as $s) {
            $values = isset($s['values']) ? $s['values'] : array();
            $subsetId = isset($s['id']) ? $s['id'] : 'unknown';
            
            // Check 1: exactly C values
            if (count($values) != $C) {
                $valid = false;
                $errMsg = "Subset #$subsetId: expected $C values, got " . count($values);
                break;
            }
            
            // Check 2 + 3: membership + consumption
            for ($i = 0; $i < $C; $i++) {
                $val = $values[$i];
                $pos = array_search($val, $workingCats[$i], true);  // Strict comparison
                
                if ($pos === false) {
                    // Value not found: either never existed or already used
                    if (in_array($val, $cats[$i], true)) {
                        $errMsg = "Subset #$subsetId, cat#$i: value $val already used";
                    } else {
                        $errMsg = "Subset #$subsetId, cat#$i: value $val not in category";
                    }
                    $valid = false;
                    break 2;
                }
                
                // Remove value from working copy (consumption)
                unset($workingCats[$i][$pos]);
                // Re-index array to keep array_search working
                $workingCats[$i] = array_values($workingCats[$i]);
            }
            
            if (!$valid) break;
            $sums[] = array_sum($values);
        }
        
        // Compute min/max/discrepancy
        $minSum = $valid && !empty($sums) ? min($sums) : null;
        $maxSum = $valid && !empty($sums) ? max($sums) : null;
        $discrepancy = ($minSum !== null && $maxSum !== null) ? $maxSum - $minSum : null;
        
        // Check optimality
        $isOptimal = false;
        if ($valid && $discrepancy !== null) {
            $isOptimal = ($discrepancy <= 1);
        }
        
        // Output
        echo "$displayPath\n";
        echo "  Expected: C=$C, M=$M, subsets=" . count($subsets);
        if ($minSum !== null) {
            echo ", min=$minSum, max=$maxSum, discrepancy=$discrepancy";
        }
        echo "; optimal: " . ($isOptimal ? 'true' : 'false') . "\n";
        
        if (!$valid) {
            echo "  Error: $errMsg\n";
            $errors++;
        } elseif ($isOptimal) {
            $optimal++;
        } else {
            $errors++;
        }
        echo "\n";
    }
}
?>