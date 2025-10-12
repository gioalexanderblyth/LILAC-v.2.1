<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once 'config.php';

try {
    // Get form data
    $awardName = $_POST['award_name'] ?? '';
    $description = $_POST['description'] ?? '';
    $uploadedFile = $_FILES['file'] ?? null;

    // Validate required fields
    if (empty($awardName) || empty($description) || !$uploadedFile) {
        throw new Exception('Missing required fields: award name, description, and file upload are required');
    }

    // Validate file upload
    validateFileUpload($uploadedFile);
    $fileType = mime_content_type($uploadedFile['tmp_name']);

    // Extract text from uploaded file
    $extractedText = extractTextFromFile($uploadedFile['tmp_name'], $fileType);
    
    if (empty($extractedText)) {
        throw new Exception('Could not extract text from the uploaded file');
    }

    // Load awards criteria dataset
    $criteriaPath = __DIR__ . '/../assets/awards-criteria.json';
    if (!file_exists($criteriaPath)) {
        throw new Exception('Awards criteria dataset not found');
    }
    
    $awardsCriteria = json_decode(file_get_contents($criteriaPath), true);
    if (!$awardsCriteria) {
        throw new Exception('Invalid awards criteria dataset');
    }

    // Analyze the extracted text against award criteria
    $analysis = analyzeTextAgainstCriteria($extractedText, $awardsCriteria);

    // Store the analysis in database (with fallback)
    $analysisId = storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile);

    // Return the analysis results
    echo json_encode([
        'success' => true,
        'analysis_id' => $analysisId,
        'detected_text' => $extractedText,
        'analysis' => $analysis,
        'recommendations' => generateRecommendations($analysis)
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Extract text from uploaded file based on file type
 */
function extractTextFromFile($filePath, $mimeType) {
    $text = '';

    switch ($mimeType) {
        case 'application/pdf':
            $text = extractTextFromPDF($filePath);
            break;
        case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
            $text = extractTextFromDOCX($filePath);
            break;
        case 'image/jpeg':
        case 'image/png':
        case 'image/jpg':
            $text = extractTextFromImage($filePath);
            break;
        default:
            throw new Exception('Unsupported file type: ' . $mimeType);
    }

    return trim($text);
}

/**
 * Extract text from PDF using simple text extraction
 */
function extractTextFromPDF($filePath) {
    // For production, you would use a proper PDF parsing library like Smalot\PdfParser
    // For now, we'll use a simple approach that works with text-based PDFs
    $content = file_get_contents($filePath);
    
    // Simple regex to extract text between stream objects
    preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches);
    
    $text = '';
    foreach ($matches[1] as $stream) {
        $text .= $stream . ' ';
    }
    
    // Clean up the text
    $text = preg_replace('/[^\w\s\.\,\!\?\;\:\-\(\)]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}

/**
 * Extract text from DOCX file
 */
function extractTextFromDOCX($filePath) {
    // For production, you would use PhpOffice\PhpWord
    // For now, we'll use a simple approach
    $zip = new ZipArchive();
    if ($zip->open($filePath) === TRUE) {
        $document = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($document) {
            // Remove XML tags and clean up
            $text = strip_tags($document);
            $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
            $text = preg_replace('/\s+/', ' ', $text);
            return trim($text);
        }
    }
    
    throw new Exception('Could not extract text from DOCX file');
}

/**
 * Extract text from image using OCR
 */
function extractTextFromImage($filePath) {
    // Get Tesseract path from config
    $tesseractPath = getTesseractPath();
    
    if (!file_exists($tesseractPath)) {
        // Fallback: try to find tesseract in PATH
        $output = shell_exec('which tesseract 2>/dev/null || where tesseract 2>nul');
        if ($output) {
            $tesseractPath = trim($output);
        } else {
            throw new Exception('Tesseract OCR not found. Please install Tesseract OCR for image text extraction.');
        }
    }
    
    // Create temporary file for output
    $outputFile = tempnam(sys_get_temp_dir(), 'ocr_output');
    
    // Run Tesseract OCR
    $command = escapeshellcmd($tesseractPath) . ' ' . escapeshellarg($filePath) . ' ' . escapeshellarg($outputFile);
    $output = shell_exec($command . ' 2>&1');
    
    if (file_exists($outputFile . '.txt')) {
        $text = file_get_contents($outputFile . '.txt');
        unlink($outputFile . '.txt');
        unlink($outputFile);
        return trim($text);
    }
    
    throw new Exception('OCR extraction failed: ' . $output);
}

/**
 * Analyze extracted text against award criteria
 */
function analyzeTextAgainstCriteria($text, $awardsCriteria) {
    $text = strtolower($text);
    $analysis = [];

    foreach ($awardsCriteria as $award) {
        $matchedCriteria = [];
        $score = 0;
        $totalCriteria = count($award['criteria']);
        
        // Check for criteria matches
        foreach ($award['criteria'] as $criterion) {
            $criterionLower = strtolower($criterion);
            
            // Check for exact matches and variations
            if (strpos($text, $criterionLower) !== false) {
                $matchedCriteria[] = $criterion;
                $score += 1;
            } else {
                // Check for partial matches (for compound phrases)
                $criterionWords = explode(' ', $criterionLower);
                if (count($criterionWords) > 1) {
                    $partialMatches = 0;
                    foreach ($criterionWords as $word) {
                        if (strlen($word) > 3 && strpos($text, $word) !== false) {
                            $partialMatches++;
                        }
                    }
                    // If 70% or more words match, consider it a partial match
                    if ($partialMatches >= count($criterionWords) * 0.7) {
                        $matchedCriteria[] = $criterion;
                        $score += 0.8;
                    }
                }
                
                // Check for semantic matches using key terms
                $semanticMatches = checkSemanticMatches($text, $criterionLower);
                if ($semanticMatches > 0) {
                    $matchedCriteria[] = $criterion;
                    $score += $semanticMatches;
                }
            }
        }
        
        // Calculate final score based on type
        $weight = getTypeWeight($award['type']);
        $finalScore = min(100, ($score / $totalCriteria) * 100 * $weight);
        
        // Determine status based on score thresholds
        $status = determineEligibilityStatus($finalScore);
        
        // Only include awards with some relevance (score > 25%)
        if ($finalScore > 25) {
            $analysis[] = [
                'award' => $award['category'],
                'score' => round($finalScore, 1),
                'status' => $status,
                'criteria_met' => $matchedCriteria,
                'total_criteria' => $totalCriteria,
                'type' => $award['type'],
                'recommendation' => generateAwardRecommendation($award, $matchedCriteria, $finalScore)
            ];
        }
    }
    
    // Sort by score descending
    usort($analysis, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return $analysis;
}

/**
 * Check for semantic matches using key terms
 */
function checkSemanticMatches($text, $criterion) {
    $semanticKeywords = [
        'leadership' => ['lead', 'manage', 'direct', 'guide', 'mentor', 'supervise', 'oversee'],
        'innovation' => ['creative', 'novel', 'new', 'breakthrough', 'pioneering', 'cutting-edge', 'advanced'],
        'sustainability' => ['green', 'environmental', 'eco-friendly', 'renewable', 'conservation', 'climate'],
        'collaboration' => ['partnership', 'cooperation', 'joint', 'alliance', 'network', 'teamwork'],
        'inclusivity' => ['diverse', 'inclusive', 'equity', 'accessibility', 'multicultural', 'integration'],
        'impact' => ['effect', 'influence', 'outcome', 'result', 'benefit', 'improvement', 'change'],
        'governance' => ['management', 'administration', 'policy', 'regulation', 'compliance', 'oversight'],
        'engagement' => ['involvement', 'participation', 'interaction', 'connection', 'relationship']
    ];
    
    $score = 0;
    foreach ($semanticKeywords as $key => $synonyms) {
        if (strpos($criterion, $key) !== false) {
            foreach ($synonyms as $synonym) {
                if (strpos($text, $synonym) !== false) {
                    $score += 0.3;
                    break;
                }
            }
        }
    }
    
    return min($score, 0.8); // Cap semantic matches at 0.8
}

/**
 * Get weight based on award type
 */
function getTypeWeight($type) {
    switch ($type) {
        case 'Individual':
            return 1.1; // Slightly higher weight for individual awards
        case 'Institutional':
            return 1.0; // Standard weight
        case 'Special':
            return 0.9; // Slightly lower weight for special awards
        default:
            return 1.0;
    }
}

/**
 * Determine eligibility status based on score
 */
function determineEligibilityStatus($score) {
    if ($score >= 80) {
        return 'Eligible';
    } elseif ($score >= 60) {
        return 'Partially Eligible';
    } else {
        return 'Not Eligible';
    }
}

/**
 * Generate recommendation for specific award
 */
function generateAwardRecommendation($award, $matchedCriteria, $score) {
    $recommendations = [];
    
    // Get category-specific recommendation template
    $categoryRecommendation = getCategorySpecificRecommendation($award['category'], $score, $matchedCriteria);
    if ($categoryRecommendation) {
        $recommendations[] = $categoryRecommendation;
    }
    
    // Add evidence-based insights
    if (count($matchedCriteria) > 0) {
        $evidenceInsight = generateEvidenceInsight($matchedCriteria, $score);
        if ($evidenceInsight) {
            $recommendations[] = $evidenceInsight;
        }
    }
    
    return implode(' ', $recommendations);
}

/**
 * Get category-specific recommendation based on score and criteria
 */
function getCategorySpecificRecommendation($category, $score, $matchedCriteria) {
    $templates = getRecommendationTemplates($category);
    
    // Filter templates based on score range
    $applicableTemplates = array_filter($templates, function($template) use ($score) {
        return $score >= $template['min_score'] && $score <= $template['max_score'];
    });
    
    if (empty($applicableTemplates)) {
        return null;
    }
    
    // Randomly select from applicable templates
    $selectedTemplate = $applicableTemplates[array_rand($applicableTemplates)];
    
    // Customize template with matched criteria if available
    $recommendation = $selectedTemplate['text'];
    if (!empty($matchedCriteria) && isset($selectedTemplate['customizable'])) {
        $sampleCriteria = implode(', ', array_slice($matchedCriteria, 0, 2));
        $recommendation = str_replace('{matched_criteria}', $sampleCriteria, $recommendation);
    }
    
    return $recommendation;
}

/**
 * Get recommendation templates for each award category
 */
function getRecommendationTemplates($category) {
    $templates = [
        'Global Citizenship Award' => [
            [
                'text' => "Encourage the institution to highlight measurable impact on intercultural understanding and student global engagement.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Consider documenting specific initiatives that foster responsible global citizenship across academic programs.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Show evidence of long-term sustainability of global awareness activities and community impact.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop programs that demonstrate active global participation and student empowerment initiatives.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Outstanding International Education Program Award' => [
            [
                'text' => "Add evidence of collaborative programs or exchange partnerships to strengthen global inclusivity and accessibility.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Highlight innovative approaches to expanding international learning access and diversity in implementation.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Document measurable outcomes of joint academic or research initiatives with international partners.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Establish long-term institutional commitment to international education program development.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Sustainability Award' => [
            [
                'text' => "Include measurable data or case studies to strengthen sustainability impact claims and environmental outcomes.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Show evidence of long-term integration of sustainability goals within academic operations and curriculum.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Highlight institutional partnerships that promote environmental responsibility and UN SDG alignment.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop innovative projects that demonstrate measurable environmental or social impact.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Best ASEAN Awareness Initiative Award' => [
            [
                'text' => "Provide examples of cross-cultural ASEAN-focused activities and measurable outcomes in regional understanding.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Highlight sustained ASEAN collaboration through student or faculty exchange programs and partnerships.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Show evidence of how the initiative strengthens regional solidarity and cross-border cooperation.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop community outreach programs that promote ASEAN identity and regional cooperation.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Emerging Leadership Award' => [
            [
                'text' => "Include measurable leadership outcomes or innovative projects led by the nominee in internationalization efforts.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Emphasize inclusivity and empowering leadership within strategic global engagement activities.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Provide testimonials or records showing mentorship, ethical governance, and measurable results.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop evidence of innovation in internationalization and strategic contribution to global engagement.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Internationalization Leadership Award' => [
            [
                'text' => "Add details on strategic international partnerships and institutional vision achievements with sustained impact.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Highlight governance reforms or inclusive strategies initiated by the leader with transformational outcomes.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Show evidence of sustained institutional and national impact through excellence in implementation.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop strategic vision and implementation plans that demonstrate commitment to inclusivity and collaboration.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Best CHED Regional Office for Internationalization Award' => [
            [
                'text' => "Provide documentation showing operational excellence and policy innovation in IZN promotion across the region.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Highlight regional programs that have measurable impact across HEIs and sustainable initiatives.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Show compliance and alignment with CHED's internationalization framework and governance standards.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop leadership and innovation strategies in IZN promotion with regional collaboration focus.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ],
        'Most Promising Regional IRO Community Award' => [
            [
                'text' => "Include evidence of collaboration, capacity building, and shared best practices among regional institutions.",
                'min_score' => 80,
                'max_score' => 100
            ],
            [
                'text' => "Highlight early success indicators and regional network activities with future potential for impact.",
                'min_score' => 60,
                'max_score' => 79
            ],
            [
                'text' => "Document mentorship or training initiatives that show strong potential for growth and inclusivity.",
                'min_score' => 40,
                'max_score' => 59
            ],
            [
                'text' => "Develop visionary leadership approaches with focus on regional connection and resource sharing.",
                'min_score' => 25,
                'max_score' => 39
            ]
        ]
    ];
    
    return $templates[$category] ?? [];
}

/**
 * Generate evidence-based insights
 */
function generateEvidenceInsight($matchedCriteria, $score) {
    $insights = [
        'high' => [
            "Strong evidence demonstrates alignment with {matched_criteria} requirements.",
            "Documentation clearly shows proficiency in {matched_criteria} areas.",
            "Excellent foundation established in {matched_criteria} with measurable outcomes."
        ],
        'medium' => [
            "Positive indicators found in {matched_criteria} with room for enhancement.",
            "Good progress demonstrated in {matched_criteria} with potential for strengthening.",
            "Solid evidence base in {matched_criteria} suggests continued development opportunities."
        ],
        'low' => [
            "Initial evidence of {matched_criteria} provides a foundation for future development.",
            "Early indicators in {matched_criteria} show potential for strategic growth.",
            "Emerging strengths in {matched_criteria} suggest focused development areas."
        ]
    ];
    
    $tone = $score >= 70 ? 'high' : ($score >= 50 ? 'medium' : 'low');
    $selectedInsight = $insights[$tone][array_rand($insights[$tone])];
    
    $sampleCriteria = implode(' and ', array_slice($matchedCriteria, 0, 2));
    return str_replace('{matched_criteria}', $sampleCriteria, $selectedInsight);
}

/**
 * Get type-specific recommendations
 */
function getTypeSpecificRecommendation($type, $score) {
    switch ($type) {
        case 'Individual':
            if ($score >= 70) {
                return "This demonstrates strong individual leadership and achievement potential.";
            }
            break;
        case 'Institutional':
            if ($score >= 70) {
                return "This shows excellent institutional commitment and programmatic excellence.";
            }
            break;
        case 'Special':
            if ($score >= 70) {
                return "This represents exceptional regional or specialized impact.";
            }
            break;
    }
    return null;
}

/**
 * Generate overall recommendations
 */
function generateRecommendations($analysis) {
    $recommendations = [];
    
    if (empty($analysis)) {
        return [
            "Analysis Summary: No strong award matches found in the current document.",
            "Recommendation Insights: Consider reviewing content alignment with ICONS Awards 2025 criteria and strengthening evidence of internationalization impact.",
            "Evidence Snippets: Focus on developing measurable outcomes and strategic documentation that demonstrates institutional commitment to global engagement."
        ];
    }
    
    $topMatch = $analysis[0];
    $goodMatches = array_filter($analysis, function($item) {
        return $item['score'] >= 60;
    });
    
    // Generate Analysis Summary
    $analysisSummary = generateAnalysisSummary($topMatch, count($goodMatches));
    $recommendations[] = $analysisSummary;
    
    // Generate Recommendation Insights
    $recommendationInsights = generateRecommendationInsights($analysis, $topMatch);
    $recommendations[] = $recommendationInsights;
    
    // Generate Evidence Snippets
    $evidenceSnippets = generateEvidenceSnippets($analysis);
    $recommendations[] = $evidenceSnippets;
    
    return $recommendations;
}

/**
 * Generate analysis summary
 */
function generateAnalysisSummary($topMatch, $goodMatchCount) {
    $summaryTemplates = [
        'high' => [
            "Analysis Summary: Exceptional alignment demonstrated with " . $topMatch['award'] . " (" . $topMatch['type'] . " category, " . $topMatch['score'] . "% confidence).",
            "Analysis Summary: Strong institutional positioning for " . $topMatch['award'] . " with compelling evidence of " . $topMatch['type'] . " excellence (" . $topMatch['score'] . "% match).",
            "Analysis Summary: Document shows outstanding potential for " . $topMatch['award'] . " recognition, demonstrating " . $topMatch['type'] . " leadership (" . $topMatch['score'] . "% alignment)."
        ],
        'medium' => [
            "Analysis Summary: Promising alignment identified with " . $topMatch['award'] . " (" . $topMatch['type'] . " category, " . $topMatch['score'] . "% confidence) with strategic enhancement opportunities.",
            "Analysis Summary: Good foundation established for " . $topMatch['award'] . " consideration, showing " . $topMatch['type'] . " potential with room for strengthening (" . $topMatch['score'] . "% match).",
            "Analysis Summary: Solid positioning for " . $topMatch['award'] . " with " . $topMatch['type'] . " strengths evident and targeted improvements recommended (" . $topMatch['score'] . "% alignment)."
        ],
        'low' => [
            "Analysis Summary: Initial alignment detected with " . $topMatch['award'] . " (" . $topMatch['type'] . " category, " . $topMatch['score'] . "% confidence) requiring strategic development.",
            "Analysis Summary: Emerging potential for " . $topMatch['award'] . " recognition with " . $topMatch['type'] . " focus areas identified for growth (" . $topMatch['score'] . "% match).",
            "Analysis Summary: Early indicators suggest " . $topMatch['award'] . " applicability with " . $topMatch['type'] . " development opportunities (" . $topMatch['score'] . "% alignment)."
        ]
    ];
    
    $tone = $topMatch['score'] >= 75 ? 'high' : ($topMatch['score'] >= 50 ? 'medium' : 'low');
    $selectedTemplate = $summaryTemplates[$tone][array_rand($summaryTemplates[$tone])];
    
    if ($goodMatchCount > 1) {
        $selectedTemplate .= " Multiple award categories identified with competitive potential.";
    }
    
    return $selectedTemplate;
}

/**
 * Generate recommendation insights
 */
function generateRecommendationInsights($analysis, $topMatch) {
    $insightTemplates = [
        'high' => [
            "Recommendation Insights: Focus on strengthening documentation of measurable impact and strategic outcomes to maximize " . $topMatch['award'] . " competitiveness.",
            "Recommendation Insights: Leverage existing " . $topMatch['type'] . " strengths to develop compelling case studies and evidence-based narratives for award submission.",
            "Recommendation Insights: Build upon current achievements to create comprehensive documentation showcasing institutional excellence in " . $topMatch['award'] . " criteria."
        ],
        'medium' => [
            "Recommendation Insights: Develop targeted strategies to enhance " . $topMatch['award'] . " alignment through focused program development and measurable outcome documentation.",
            "Recommendation Insights: Strengthen " . $topMatch['type'] . " positioning by expanding evidence base and implementing strategic improvements aligned with award criteria.",
            "Recommendation Insights: Create action plan to build upon current foundation and develop comprehensive approach to " . $topMatch['award'] . " requirements."
        ],
        'low' => [
            "Recommendation Insights: Establish strategic roadmap for developing " . $topMatch['award'] . " capabilities with focus on " . $topMatch['type'] . " program enhancement and evidence building.",
            "Recommendation Insights: Create comprehensive development plan to strengthen institutional capacity in areas aligned with " . $topMatch['award'] . " criteria and expectations.",
            "Recommendation Insights: Develop systematic approach to building " . $topMatch['type'] . " excellence with targeted initiatives that support " . $topMatch['award'] . " objectives."
        ]
    ];
    
    $tone = $topMatch['score'] >= 75 ? 'high' : ($topMatch['score'] >= 50 ? 'medium' : 'low');
    $selectedTemplate = $insightTemplates[$tone][array_rand($insightTemplates[$tone])];
    
    // Add multi-category guidance if applicable
    $types = array_unique(array_column($analysis, 'type'));
    if (count($types) > 1) {
        $selectedTemplate .= " Consider strategic focus on strongest " . $topMatch['type'] . " category for optimal award positioning.";
    }
    
    return $selectedTemplate;
}

/**
 * Generate evidence snippets
 */
function generateEvidenceSnippets($analysis) {
    $topMatches = array_slice($analysis, 0, 3); // Top 3 matches
    $evidenceText = "Evidence Snippets: ";
    
    $snippetTemplates = [
        "Key strengths identified in {criteria} with {score}% alignment demonstrating {type} excellence.",
        "Strong evidence base found in {criteria} showing {score}% match for {type} category recognition.",
        "Compelling indicators of {criteria} with {score}% confidence level supporting {type} award potential."
    ];
    
    $snippets = [];
    foreach ($topMatches as $match) {
        $template = $snippetTemplates[array_rand($snippetTemplates)];
        $sampleCriteria = !empty($match['criteria_met']) ? implode(', ', array_slice($match['criteria_met'], 0, 2)) : 'core competencies';
        $snippet = str_replace(['{criteria}', '{score}', '{type}'], [$sampleCriteria, $match['score'], strtolower($match['type'])], $template);
        $snippets[] = $snippet;
    }
    
    return $evidenceText . implode(' ', $snippets);
}

/**
 * Store analysis results in database with fallback
 */
function storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile) {
    global $pdo;
    
    try {
        // Upload file to uploads directory
        $uploadDir = __DIR__ . '/../uploads/awards/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . '_' . time() . '_' . basename($uploadedFile['name']);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Check if we're using file-based fallback
        if ($pdo instanceof FileBasedDatabase) {
            // Store analysis in file system as fallback
            $analysisData = [
                'title' => $awardName,
                'description' => $description,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'detected_text' => $extractedText,
                'analysis_results' => json_encode($analysis),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $dataDir = __DIR__ . '/../data/';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            $analysisFile = $dataDir . 'analysis_' . time() . '_' . uniqid() . '.json';
            file_put_contents($analysisFile, json_encode($analysisData));
            
            logActivity('Analysis stored in file-based fallback: ' . $analysisFile, 'INFO');
            
            // Return a mock ID
            return 'file_' . time();
        }
        
        // Store in SQLite database
        $stmt = $pdo->prepare("
            INSERT INTO award_analysis (
                title, 
                description, 
                file_name, 
                file_path, 
                detected_text, 
                analysis_results, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $analysisJson = json_encode($analysis);
        
        $stmt->execute([
            $awardName,
            $description,
            $fileName,
            $filePath,
            $extractedText,
            $analysisJson
        ]);
        
        return $pdo->lastInsertId();
        
    } catch (Exception $e) {
        // If database storage fails, still save the file and return a mock ID
        logActivity('Database storage failed, using file fallback: ' . $e->getMessage(), 'WARNING');
        
        if (isset($fileName) && isset($filePath)) {
            return 'fallback_' . time();
        }
        
        throw new Exception('Failed to store analysis results: ' . $e->getMessage());
    }
}

// Database connection is initialized in config.php
?>
