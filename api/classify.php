<?php
function load_rules() {
    $file = dirname(__DIR__) . '/data/criteria/awards-rules.json';
    if (!file_exists($file)) { return []; }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

function tokenize($text) {
    $text = strtolower($text ?? '');
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
    $parts = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    return $parts ?: [];
}

function jaccard_similarity($setA, $setB) {
    if (empty($setA) && empty($setB)) return 1.0;
    if (empty($setA) || empty($setB)) return 0.0;
    
    $intersection = array_intersect($setA, $setB);
    $union = array_unique(array_merge($setA, $setB));
    
    return count($intersection) / count($union);
}

function weighted_jaccard($textTokens, $criteriaTokens, $weights = []) {
    // Create weighted token sets based on frequency and importance
    $textWeighted = [];
    foreach ($textTokens as $token) {
        $weight = $weights[$token] ?? 1.0;
        for ($i = 0; $i < $weight; $i++) {
            $textWeighted[] = $token;
        }
    }
    
    $criteriaWeighted = [];
    foreach ($criteriaTokens as $token) {
        $weight = $weights[$token] ?? 1.0;
        for ($i = 0; $i < $weight; $i++) {
            $criteriaWeighted[] = $token;
        }
    }
    
    return jaccard_similarity($textWeighted, $criteriaWeighted);
}

function checklist_eval($tokens, $criteriaRules) {
    $out = [];
    foreach ($criteriaRules as $crit => $data) {
        $criteriaTokens = array_keys($data['keywords'] ?? []);
        $weights = $data['keywords'] ?? [];
        $score = weighted_jaccard($tokens, $criteriaTokens, $weights);
        $out[$crit] = $score >= 0.7 ? 'met' : ($score >= 0.35 ? 'partial' : 'missing');
    }
    return $out;
}

function classify_text($text, $meta = []) {
    $rules = load_rules();
    $tokens = tokenize($text);
    $categories = $rules['categories'] ?? [];
    $scores = [];
    $checklists = [];
    foreach ($categories as $catKey => $cat) {
        $criteriaTokens = array_keys($cat['keywords'] ?? []);
        $weights = $cat['keywords'] ?? [];
        $kwScore = weighted_jaccard($tokens, $criteriaTokens, $weights);
        $crit = checklist_eval($tokens, $cat['criteria'] ?? []);
        $bonus = 0.0;
        if (!empty($meta['role']) && in_array($meta['role'], $cat['roles'] ?? [])) { $bonus += 0.15; }
        $scores[$catKey] = max(0.0, min(1.0, ($kwScore * 0.75) + $bonus));
        $checklists[$catKey] = $crit;
    }
    arsort($scores);
    $predKey = array_key_first($scores);
    $pred = $categories[$predKey]['name'] ?? $predKey;
    $confidence = $scores[$predKey] ?? 0.0;
    $matched = [];
    foreach ($scores as $k => $s) { if ($s >= ($rules['match_threshold'] ?? 0.5)) { $matched[] = ['key'=>$k,'name'=>$categories[$k]['name'],'score'=>$s]; } }
    $predChecklist = $checklists[$predKey] ?? [];
    $recs = $rules['recommendations'][$predKey] ?? 'Provide additional documentation to strengthen eligibility.';
    $evidence = ['top_tokens' => array_slice($tokens, 0, 30)];
    return [
        'predicted_category' => $pred,
        'confidence' => $confidence,
        'matched_categories' => $matched,
        'checklist' => $predChecklist,
        'recommendations' => $recs,
        'evidence' => $evidence
    ];
}
?>


