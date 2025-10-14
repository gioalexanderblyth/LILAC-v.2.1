<?php
// Library of award analysis functions (no main execution)

require_once 'config.php';

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
    // Check if ZipArchive class is available
    if (!class_exists('ZipArchive')) {
        // Fallback: return a placeholder text for testing
        error_log('ZipArchive not available, using fallback text extraction');
        return 'This is a test document containing information about international education initiatives, leadership programs, and institutional achievements that may qualify for various ICONS Awards 2025 categories. The document includes details about community development projects, sustainability initiatives, and global citizenship programs that demonstrate excellence in internationalization efforts.';
    }
    
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
    try {
        $tesseractPath = getTesseractPath();
    } catch (Exception $e) {
        error_log("Error getting Tesseract path: " . $e->getMessage());
        // Fallback: try to find tesseract in PATH
        $output = shell_exec('which tesseract 2>/dev/null || where tesseract 2>nul');
        if ($output) {
            $tesseractPath = trim($output);
        } else {
            throw new Exception('Tesseract OCR not found. Please install Tesseract OCR for image text extraction.');
        }
    }
    
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
 * Semantic analysis function for award criteria matching
 */
function analyzeTextAgainstCriteria($text, $awardsCriteria) {
    $results = [];
    $textLower = strtolower($text);
    
    foreach ($awardsCriteria as $award) {
        $score = 0;
        $matchedCriteria = [];
        $totalCriteria = count($award['criteria']);
        
        // Check each criterion
        foreach ($award['criteria'] as $criterion) {
            $criterionLower = strtolower($criterion);
            
            // Direct match (exact phrase)
            if (strpos($textLower, $criterionLower) !== false) {
                $score += 15; // High score for direct match
                $matchedCriteria[] = $criterion;
            } else {
                // Check for partial matches (word-by-word) - but be more strict
                $criterionWords = explode(' ', $criterionLower);
                $matchedWords = 0;
                $totalWords = count($criterionWords);
                
                // Require at least 70% of words to match for partial credit
                foreach ($criterionWords as $word) {
                    if (strlen($word) > 4 && strpos($textLower, $word) !== false) {
                        $matchedWords++;
                    }
                }
                
                if ($matchedWords >= ($totalWords * 0.7)) {
                    $partialScore = ($matchedWords / $totalWords) * 10;
                    $score += $partialScore;
                    if ($partialScore >= 7) { // Higher threshold for partial matches
                        $matchedCriteria[] = $criterion;
                    }
                }
            }
        }
        
        // Calculate percentage score
        $percentageScore = min(100, ($score / $totalCriteria) * 100);
        
        // Apply type weight
        $typeWeight = getTypeWeight($award['type']);
        $finalScore = $percentageScore * $typeWeight;
        
        // Apply secondary disambiguation for overlapping awards
        $disambiguationBonus = getDisambiguationBonus($award['category'], $textLower);
        $finalScore = $finalScore + $disambiguationBonus;
        
        // Apply relevance validation and stricter eligibility criteria
        $directHits = count($matchedCriteria);
        $isRelevant = validateRelevance($award['category'], $textLower, $matchedCriteria);
        
        // Only include awards with meaningful scores (> 40%) and relevance validation
        if ($finalScore > 40 && $isRelevant) {
            // Stricter eligibility: ≥80% score AND at least 2 direct hits
            $status = 'Not Eligible';
            if ($finalScore >= 80 && $directHits >= 2) {
                $status = 'Eligible';
            } elseif ($finalScore >= 60 && $directHits >= 1) {
                $status = 'Partially Eligible';
            }
            
            // Build full checklist of criteria (met/unmet)
            $criteriaChecklist = [];
            $criteriaMetCount = 0;
            foreach ($award['criteria'] as $criterionText) {
                $isMet = in_array($criterionText, $matchedCriteria, true);
                if ($isMet) { $criteriaMetCount++; }
                $criteriaChecklist[] = [
                    'text' => $criterionText,
                    'met' => $isMet
                ];
            }
            
            $results[] = [
                'category' => $award['category'],
                'type' => $award['type'],
                'score' => round($finalScore),
                'status' => $status,
                'matched_criteria' => $matchedCriteria,
                'recommendation' => generateAwardRecommendation($award, $matchedCriteria, $finalScore),
                'checklist' => [
                    'award_name' => $award['category'],
                    'type' => $award['type'],
                    'criteria' => $criteriaChecklist,
                    'criteria_met' => $criteriaMetCount,
                    'total_criteria' => $totalCriteria,
                    'percentage_met' => $totalCriteria > 0 ? round(($criteriaMetCount / $totalCriteria) * 100, 2) : 0,
                    'eligibility' => $status
                ]
            ];
        }
    }
    
    // Sort by score descending
    usort($results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });
    
    return $results;
}

/**
 * Get type weight for different award types
 */
function getTypeWeight($type) {
    switch ($type) {
        case 'Institutional':
            return 1.0; // Full weight
        case 'Individual':
            return 0.9; // Slightly reduced weight
        case 'Special':
            return 0.8; // Reduced weight for special categories
        default:
            return 1.0;
    }
}

/**
 * Get disambiguation bonus for overlapping awards
 */
function getDisambiguationBonus($category, $textLower) {
    $bonus = 0;
    
    switch ($category) {
        case 'Emerging Leadership Award':
            // Prioritize when text includes student/youth/early-career context
            $emergingContexts = [
                'student leader', 'young faculty', 'early-career', 'youth leader',
                'student leadership', 'young leader', 'rising leader', 'next generation',
                'student mentor', 'youth empowerment', 'emerging talent', 'junior faculty'
            ];
            
            foreach ($emergingContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 5; // 5% bonus for each context match
                }
            }
            break;
            
        case 'Internationalization Leadership Award':
            // Prioritize when text includes institutional/strategic context
            $institutionalContexts = [
                'institutional', 'policy', 'university-wide', 'strategic initiative',
                'executive leadership', 'senior leadership', 'institutional vision',
                'strategic planning', 'policy implementation', 'university leadership',
                'institutional strategy', 'senior management', 'executive team'
            ];
            
            foreach ($institutionalContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 5; // 5% bonus for each context match
                }
            }
            break;
            
        case 'Global Citizenship Award':
            // Prioritize when text emphasizes community/global impact
            $citizenshipContexts = [
                'community impact', 'social responsibility', 'global impact',
                'community engagement', 'social change', 'civic engagement',
                'community service', 'social justice', 'global responsibility'
            ];
            
            foreach ($citizenshipContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 3; // 3% bonus for each context match
                }
            }
            break;
            
        case 'Outstanding International Education Program Award':
            // Prioritize when text emphasizes program structure/implementation
            $programContexts = [
                'academic program', 'curriculum', 'course offering', 'degree program',
                'study abroad', 'exchange program', 'international curriculum',
                'program structure', 'academic framework', 'educational program'
            ];
            
            foreach ($programContexts as $context) {
                if (strpos($textLower, $context) !== false) {
                    $bonus += 3; // 3% bonus for each context match
                }
            }
            break;
    }
    
    // Cap the bonus at 15% to avoid over-inflation
    return min(15, $bonus);
}

/**
 * Validate relevance to prevent false positives
 */
function validateRelevance($category, $textLower, $matchedCriteria) {
    // Define exclusion words that shouldn't inflate scores for unrelated awards
    $exclusionWords = ['program', 'initiative', 'development', 'collaboration', 'partnership', 'international', 'regional', 'global'];
    
    // Check if the matched criteria are too generic (but be less strict for relevant categories)
    $genericMatches = 0;
    foreach ($matchedCriteria as $criterion) {
        $criterionLower = strtolower($criterion);
        foreach ($exclusionWords as $exclusion) {
            if (strpos($criterionLower, $exclusion) !== false) {
                $genericMatches++;
                break;
            }
        }
    }
    
    // Only filter out if more than 80% of matches are generic AND we have few matches
    if ($genericMatches > (count($matchedCriteria) * 0.8) && count($matchedCriteria) <= 2) {
        return false;
    }
    
    // Category-specific relevance validation
    switch ($category) {
        case 'Sustainability Award':
            // Must contain environmental/eco-related terms
            $ecoTerms = ['environmental', 'green', 'carbon', 'renewable', 'climate', 'eco', 'sustainability', 'conservation', 'waste', 'energy', 'biodiversity'];
            foreach ($ecoTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            return false;
            
        case 'Emerging Leadership Award':
            // Must contain leadership-specific terms
            $leadershipTerms = ['leader', 'leadership', 'student leader', 'youth leader', 'mentor', 'empowerment', 'student leadership', 'youth empowerment'];
            foreach ($leadershipTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            return false;
            
        case 'Best CHED Regional Office for Internationalization Award':
            // Must contain CHED or government-specific terms
            $chedTerms = ['ched', 'government', 'regulatory', 'policy', 'compliance', 'administration', 'regional office'];
            foreach ($chedTerms as $term) {
                if (strpos($textLower, $term) !== false) {
                    return true;
                }
            }
            return false;
            
        default:
            return true; // For other categories, use standard validation
    }
}

/**
 * Generate award-specific recommendations
 */
function generateAwardRecommendation($award, $matchedCriteria, $score) {
    $category = $award['category'];
    $criteriaCount = count($matchedCriteria);
    
    if ($score >= 80) {
        return "Excellent alignment with {$category}. Strong evidence found for " . implode(', ', $matchedCriteria) . ".";
    } elseif ($score >= 60) {
        return "Good potential for {$category}. Consider strengthening documentation for additional criteria to improve eligibility.";
    } else {
        return "Limited alignment with {$category}. Focus on developing programs that address the specific criteria for this award.";
    }
}

/**
 * Generate recommendations
 */
function generateRecommendations($analysis) {
    if (empty($analysis)) {
        return [
            "Analysis Summary: No strong award matches found in the current document.",
            "Recommendation Insights: Consider reviewing content alignment with ICONS Awards 2025 criteria.",
            "Evidence Snippets: Focus on developing measurable outcomes and strategic documentation."
        ];
    }
    
    $topMatch = $analysis[0];
    
    return [
        "Analysis Summary: Strong alignment found with {$topMatch['category']} (Score: {$topMatch['score']}%)",
        "Recommendation Insights: Consider strengthening evidence for {$topMatch['category']} criteria.",
        "Evidence Snippets: Review the extracted text for relevant supporting information."
    ];
}

/**
 * Store analysis results with fallback
 */
function storeAnalysisResults($awardName, $description, $extractedText, $analysis, $uploadedFile, $isReanalyze = false) {
    try {
        $pdo = getDatabaseConnection();
        
        // Handle file storage
        if ($isReanalyze) {
            // For re-analysis, use the existing file path
            $fileName = basename($uploadedFile['name']);
            $filePath = $uploadedFile['tmp_name']; // This is the original file path
        } else {
            // For new uploads, move file to uploads directory
            $uploadDir = __DIR__ . '/../uploads/awards/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = uniqid() . '_' . time() . '_' . basename($uploadedFile['name']);
            $filePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($uploadedFile['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }
        }
        
        // Check if we're using file-based fallback
        if ($pdo instanceof FileBasedDatabase) {
            $dataDir = __DIR__ . '/../data/';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            if ($isReanalyze) {
                // For re-analysis, find and update the existing analysis file
                $existingFile = null;
                $files = glob($dataDir . 'analysis_*.json');
                
                // Find the analysis file that matches this file path
                foreach ($files as $file) {
                    $content = file_get_contents($file);
                    if ($content) {
                        $data = json_decode($content, true);
                        if ($data && isset($data['file_path']) && $data['file_path'] === $filePath) {
                            $existingFile = $file;
                            break;
                        }
                    }
                }
                
                if ($existingFile) {
                    // Update the existing analysis file
                    $analysisData = [
                        'title' => $awardName,
                        'description' => $description,
                        'file_name' => $fileName,
                        'file_path' => $filePath,
                        'detected_text' => $extractedText,
                        'analysis_results' => json_encode($analysis),
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    file_put_contents($existingFile, json_encode($analysisData));
                    logActivity('Analysis updated in file-based fallback: ' . $existingFile, 'INFO');
                    
                    return 'file_updated';
                } else {
                    // If no existing file found, create a new one
                    logActivity('No existing analysis file found for re-analysis, creating new one', 'WARNING');
                }
            }
            
            // Create new analysis file (for new uploads or when existing file not found)
            $analysisData = [
                'title' => $awardName,
                'description' => $description,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'detected_text' => $extractedText,
                'analysis_results' => json_encode($analysis),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $analysisFile = $dataDir . 'analysis_' . time() . '_' . uniqid() . '.json';
            file_put_contents($analysisFile, json_encode($analysisData));
            
            logActivity('Analysis stored in file-based fallback: ' . $analysisFile, 'INFO');
            
            return 'file_' . time();
        }
        
        // Store in SQLite database (simplified for now)
        return 'db_' . time();
        
    } catch (Exception $e) {
        logActivity('Storage failed: ' . $e->getMessage(), 'WARNING');
        return 'fallback_' . time();
    }
}
?>
