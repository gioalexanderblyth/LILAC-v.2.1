<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'config.php';

// Load award criteria data
function loadAwardsCriteria() {
    $file = dirname(__DIR__) . '/data/criteria/awards-criteria.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

// Load award requirements data
function loadAwardRequirements() {
    $file = dirname(__DIR__) . '/data/criteria/award_requirements.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    return json_decode($json, true) ?: [];
}

try {
    $pdo = getDatabaseConnection();
    $awardsCriteria = loadAwardsCriteria();
    $awardRequirements = loadAwardRequirements();
    
    // Define the 8 awards we want to track
    $targetAwards = [
        'Global Citizenship Award',
        'Outstanding International Education Program Award',
        'Sustainability Award',
        'Best ASEAN Awareness Initiative Award',
        'Emerging Leadership Award',
        'Internationalization Leadership Award',
        'Best CHED Regional Office for Internationalization Award',
        'Most Promising Regional IRO Community Award'
    ];
    
    $analyticsData = [];
    
    foreach ($targetAwards as $awardName) {
        // Find criteria for this award
        $criteria = [];
        $totalCriteria = 0;
        foreach ($awardsCriteria as $award) {
            if ($award['category'] === $awardName) {
                $criteria = $award['criteria'];
                $totalCriteria = count($criteria);
                break;
            }
        }
        
        // Initialize counters
        $criteriaMet = 0;
        $totalSubmissions = 0;
        $totalEligible = 0;
        $totalPartiallyEligible = 0;
        $recentRemark = 'No submissions yet';
        
        // Check if we're using file-based fallback or SQLite
        if ($pdo instanceof FileBasedDatabase) {
            // Mock data for file-based fallback
            $mockData = [
                'Global Citizenship Award' => ['met' => 4, 'total' => 6, 'remark' => 'Global engagement initiatives progressing'],
                'Outstanding International Education Program Award' => ['met' => 5, 'total' => 5, 'remark' => 'All requirements satisfied'],
                'Sustainability Award' => ['met' => 3, 'total' => 7, 'remark' => 'Environmental initiatives in progress'],
                'Best ASEAN Awareness Initiative Award' => ['met' => 6, 'total' => 8, 'remark' => 'Strong ASEAN partnership network'],
                'Emerging Leadership Award' => ['met' => 4, 'total' => 5, 'remark' => 'Leadership development programs active'],
                'Internationalization Leadership Award' => ['met' => 7, 'total' => 7, 'remark' => 'Exemplary internationalization leadership'],
                'Best CHED Regional Office for Internationalization Award' => ['met' => 2, 'total' => 4, 'remark' => 'Regional office coordination needed'],
                'Most Promising Regional IRO Community Award' => ['met' => 3, 'total' => 6, 'remark' => 'IRO community building in progress']
            ];
            
            $data = $mockData[$awardName] ?? ['met' => 0, 'total' => $totalCriteria, 'remark' => 'No data available'];
            $criteriaMet = $data['met'];
            $totalCriteria = $data['total'];
            $recentRemark = $data['remark'];
        } else {
            // Get real data from database
            try {
                // Get all analysis results for this award
                $stmt = $pdo->prepare("
                    SELECT aa.analysis_results, aa.created_at
                    FROM award_analysis aa
                    WHERE aa.analysis_results LIKE ?
                    ORDER BY aa.created_at DESC
                ");
                $stmt->execute(['%' . $awardName . '%']);
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $totalSubmissions = count($results);
                $allMatchedCriteria = [];
                
                foreach ($results as $result) {
                    if ($result['analysis_results']) {
                        $analysisData = json_decode($result['analysis_results'], true);
                        if (is_array($analysisData)) {
                            foreach ($analysisData as $analysis) {
                                if (isset($analysis['category']) && $analysis['category'] === $awardName) {
                                    if ($analysis['status'] === 'Eligible') {
                                        $totalEligible++;
                                    } elseif ($analysis['status'] === 'Partially Eligible') {
                                        $totalPartiallyEligible++;
                                    }
                                    
                                    if (isset($analysis['matched_criteria']) && is_array($analysis['matched_criteria'])) {
                                        foreach ($analysis['matched_criteria'] as $matched) {
                                            if (!in_array($matched, $allMatchedCriteria)) {
                                                $allMatchedCriteria[] = $matched;
                                            }
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Calculate criteria met based on how many criteria were matched across all submissions
                $criteriaMet = count($allMatchedCriteria);
                
                // Generate remark based on data
                if ($totalEligible > 0) {
                    $recentRemark = "Excellent progress - {$totalEligible} eligible submission" . ($totalEligible > 1 ? 's' : '');
                } elseif ($totalPartiallyEligible > 0) {
                    $recentRemark = "Good progress - {$totalPartiallyEligible} partially eligible submission" . ($totalPartiallyEligible > 1 ? 's' : '');
                } elseif ($totalSubmissions > 0) {
                    $recentRemark = "{$totalSubmissions} submission" . ($totalSubmissions > 1 ? 's' : '') . " analyzed - continue improving criteria alignment";
                } else {
                    $recentRemark = "No submissions yet for this award";
                }
                
            } catch (Exception $e) {
                // Fallback to criteria-based calculation
                $criteriaMet = 0;
                $recentRemark = 'Database query failed - using default values';
            }
        }
        
        // Calculate progress percentage
        $progressPercentage = $totalCriteria > 0 ? round(($criteriaMet / $totalCriteria) * 100) : 0;
        
        // Determine progress color based on percentage
        $progressColor = 'red-500';
        if ($progressPercentage >= 80) {
            $progressColor = 'green-500';
        } elseif ($progressPercentage >= 60) {
            $progressColor = 'blue-500';
        } elseif ($progressPercentage >= 40) {
            $progressColor = 'yellow-500';
        }
        
        $analyticsData[] = [
            'award_name' => $awardName,
            'criteria_met' => $criteriaMet,
            'total_criteria' => $totalCriteria,
            'progress_percentage' => $progressPercentage,
            'progress_color' => $progressColor,
            'remark' => $recentRemark,
            'criteria' => $criteria
        ];
    }
    
    // Generate status analysis data for the chart based on progress percentages (like progress bars)
    $statusAnalysis = [
        'fully_met' => 0,
        'partially_met' => 0,
        'under_review' => 0,
        'unqualified' => 0
    ];
    
    // Calculate overall progress distribution from the analytics data
    $totalProgress = 0;
    $totalCriteria = 0;
    $eligibleProgress = 0;    // 80%+ progress awards
    $partialProgress = 0;     // 60-79% progress awards  
    $underReviewProgress = 0; // 40-59% progress awards
    $unqualifiedProgress = 0; // <40% progress awards
    
    // Generate trends data (last 10 months)
    $trendsData = [];
    $eligibleTrends = [];
    $totalTrends = [];
    
    // Generate analysis and recommendations
    $recommendations = [];
    
    if ($pdo instanceof FileBasedDatabase) {
        // Check if there are any actual upload files in file-based system
        $dataDir = __DIR__ . '/../data/';
        $hasUploads = false;
        
        if (is_dir($dataDir)) {
            $files = glob($dataDir . '*analysis*.json');
            $hasUploads = count($files) > 0;
        }
        
        if (!$hasUploads) {
            // No uploads yet - show empty gray state
            $statusAnalysis = [
                'fully_met' => 0,
                'partially_met' => 0,
                'under_review' => 0,
                'unqualified' => 100
            ];
        } else {
            // Mock data for demonstration (when there are uploads)
            $statusAnalysis = [
                'fully_met' => 45,
                'partially_met' => 25,
                'under_review' => 20,
                'unqualified' => 10
            ];
        }
        
        $eligibleTrends = [10, 18, 12, 5, 8, 11, 14, 15, 12, 11];
        $totalTrends = [12, 22, 15, 8, 12, 16, 18, 20, 16, 14];
        
        $recommendations = [
            [
                'icon' => 'check_circle',
                'iconColor' => 'green-600',
                'text' => 'Your department achieved <span class="font-bold text-green-700 dark:text-green-400">80%</span> of award qualifications this cycle.',
                'type' => 'success'
            ],
            [
                'icon' => 'lightbulb',
                'iconColor' => 'yellow-600',
                'text' => 'Focus on completing missing supporting documents for Sustainability Award.',
                'type' => 'suggestion'
            ],
            [
                'icon' => 'trending_up',
                'iconColor' => 'blue-600',
                'text' => 'Strong performance on <span class="font-semibold">leadership-based awards.</span>',
                'type' => 'insight'
            ],
            [
                'icon' => 'groups',
                'iconColor' => 'purple-600',
                'text' => 'Consider collaborating with ASEAN partners to improve regional engagement metrics.',
                'type' => 'collaboration'
            ]
        ];
    } else {
        // Get real data from database
        try {
            // Calculate status analysis
            $stmt = $pdo->query("
                SELECT 
                    COUNT(CASE WHEN analysis_results LIKE '%\"status\":\"Eligible\"%' THEN 1 END) as fully_met,
                    COUNT(CASE WHEN analysis_results LIKE '%\"status\":\"Partially Eligible\"%' THEN 1 END) as partially_met,
                    COUNT(CASE WHEN analysis_results LIKE '%\"status\":\"Under Review\"%' THEN 1 END) as under_review,
                    COUNT(CASE WHEN analysis_results LIKE '%\"status\":\"Not Eligible\"%' THEN 1 END) as unqualified
                FROM award_analysis 
                WHERE analysis_results IS NOT NULL
            ");
            
            $statusResult = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($statusResult) {
                $statusAnalysis = [
                    'fully_met' => (int)$statusResult['fully_met'],
                    'partially_met' => (int)$statusResult['partially_met'],
                    'under_review' => (int)$statusResult['under_review'],
                    'unqualified' => (int)$statusResult['unqualified']
                ];
            }
            
            // Generate trends data for last 10 months
            for ($i = 9; $i >= 0; $i--) {
                $month = date('Y-m', strtotime("-$i months"));
                $nextMonth = date('Y-m', strtotime("-$i months +1 month"));
                
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(CASE WHEN analysis_results LIKE '%\"status\":\"Eligible\"%' THEN 1 END) as eligible,
                        COUNT(*) as total
                    FROM award_analysis 
                    WHERE created_at >= ? AND created_at < ?
                ");
                $stmt->execute([$month . '-01', $nextMonth . '-01']);
                $monthData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $eligibleTrends[] = (int)($monthData['eligible'] ?? 0);
                $totalTrends[] = (int)($monthData['total'] ?? 0);
            }
            
            // Generate dynamic recommendations based on actual data
            $totalSubmissions = array_sum($statusAnalysis);
            $successRate = $totalSubmissions > 0 ? round((($statusAnalysis['fully_met'] + $statusAnalysis['partially_met']) / $totalSubmissions) * 100) : 0;
            
            $recommendations = [
                [
                    'icon' => 'check_circle',
                    'iconColor' => 'green-600',
                    'text' => "Your department achieved <span class=\"font-bold text-green-700 dark:text-green-400\">{$successRate}%</span> of award qualifications this cycle.",
                    'type' => 'success'
                ],
                [
                    'icon' => 'lightbulb',
                    'iconColor' => 'yellow-600',
                    'text' => $statusAnalysis['under_review'] > 0 ? "You have {$statusAnalysis['under_review']} submissions under review that need attention." : 'Great! No pending reviews.',
                    'type' => 'suggestion'
                ],
                [
                    'icon' => 'trending_up',
                    'iconColor' => 'blue-600',
                    'text' => $statusAnalysis['fully_met'] > $statusAnalysis['unqualified'] ? 'Strong performance on <span class="font-semibold">award submissions</span>.' : 'Consider improving submission quality.',
                    'type' => 'insight'
                ],
                [
                    'icon' => 'groups',
                    'iconColor' => 'purple-600',
                    'text' => 'Continue building partnerships and documenting impact for better award outcomes.',
                    'type' => 'collaboration'
                ]
            ];
            
        } catch (Exception $e) {
            // Use default data if query fails
            logActivity('Failed to get analytics dashboard data: ' . $e->getMessage(), 'WARNING');
        }
    }
    
    // Add a flag to indicate if we have any uploads at all
    $totalSubmissions = $statusAnalysis['fully_met'] + $statusAnalysis['partially_met'] + $statusAnalysis['under_review'] + $statusAnalysis['unqualified'];
    $hasAnyUploads = $totalSubmissions > 0;
    
    echo json_encode([
        'success' => true,
        'data' => $analyticsData,
        'status_analysis' => $statusAnalysis,
        'has_uploads' => $hasAnyUploads,
        'trends' => [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
            'eligible' => $eligibleTrends,
            'total' => $totalTrends
        ],
        'recommendations' => $recommendations,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
