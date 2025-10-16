<?php
// Suppress PHP errors to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clean any existing output
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

// Set error handler to catch any PHP errors
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User, X-Role');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get user info from headers
$user = $_SERVER['HTTP_X_USER'] ?? 'anonymous';
$role = $_SERVER['HTTP_X_ROLE'] ?? 'user';

try {
    // Include config for database connection with fallback
    require_once 'config.php';
    
    // Get database connection from config (includes fallback)
    $pdo = getDatabaseConnection();
    
    // Check if we're using file-based fallback
    $isFileBased = ($pdo instanceof FileBasedDatabase);
    
    // Log the upload attempt
    error_log("Upload attempt - File count: " . count($_FILES) . ", POST count: " . count($_POST));
    
    // Check if file was uploaded
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['file'];
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only PDF, DOCX, JPG, and PNG files are allowed.');
    }
    
    // Create uploads directory if it doesn't exist
    $uploadDir = '../uploads/awards/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = uniqid() . '_' . time() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Extract text based on file type (simulated OCR)
    $extractedText = simulateOCRExtraction($file['name']);
    error_log("[$timestamp] Extracted text for file '" . $file['name'] . "': " . substr($extractedText, 0, 100) . "...");
    
    if (empty($extractedText)) {
        throw new Exception('Could not extract text from the uploaded file');
    }
    
    // Load ICONS 2025 awards dataset (updated schema)
    $criteriaPath = __DIR__ . '/../assets/icons2025_awards.json';
    if (!file_exists($criteriaPath)) {
        throw new Exception('ICONS 2025 awards dataset not found');
    }
    $iconsDataset = json_decode(file_get_contents($criteriaPath), true);
    if (!$iconsDataset || !is_array($iconsDataset)) {
        throw new Exception('Invalid ICONS 2025 dataset format');
    }
    
    // Perform analysis with Jaccard + semantic boost + category weights
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] FRESH UPLOAD ANALYSIS STARTED for file: " . $file['name']);
    $analysisResults = performIconsAnalysis($extractedText, $iconsDataset);
    error_log("[$timestamp] FRESH UPLOAD ANALYSIS COMPLETED - Results count: " . count($analysisResults));
    
    $title = $_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME);
    $date = $_POST['date'] ?? date('Y-m-d');
    $description = $_POST['description'] ?? '';
    
    $analysisResultsJson = json_encode($analysisResults);
    
    // Handle database storage with fallback
    if ($isFileBased) {
        // Store analysis in file system as fallback
        $analysisData = [
            'title' => $title,
            'description' => $description,
            'file_name' => $file['name'],
            'file_path' => $filePath,
            'detected_text' => $extractedText,
            'analysis_results' => $analysisResultsJson,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $dataDir = __DIR__ . '/../data/';
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        $analysisFile = $dataDir . 'upload_analysis_' . time() . '_' . uniqid() . '.json';
        file_put_contents($analysisFile, json_encode($analysisData));
        
        logActivity('Upload analysis stored in file-based fallback: ' . $analysisFile, 'INFO');
        
        // Return a mock ID
        $awardId = 'upload_file_' . time();
    } else {
        // Save analysis results directly to award_analysis table
        $stmt = $pdo->prepare("
            INSERT INTO award_analysis (title, description, file_name, file_path, detected_text, analysis_results, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$title, $description, $file['name'], $filePath, $extractedText, $analysisResultsJson, date('Y-m-d H:i:s')]);
        $awardId = $pdo->lastInsertId();
    }
    
    // Build counts for eligibility
    $eligibleCount = 0; $partialCount = 0; $notEligibleCount = 0;
    foreach ($analysisResults as $r) {
        $status = strtolower($r['status'] ?? '');
        if ($status === 'eligible') { $eligibleCount++; }
        elseif ($status === 'partially eligible') { $partialCount++; }
        else { $notEligibleCount++; }
    }
    
    // Return success response
    $response = [
        'success' => true,
        'message' => 'File uploaded and analyzed successfully',
        'award_id' => $awardId,
        'analysis' => $analysisResults,
        'eligible_count' => $eligibleCount,
        'partial_count' => $partialCount,
        'not_eligible_count' => $notEligibleCount,
        'total_count' => count($analysisResults)
    ];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
    
} catch (Exception $e) {
    // Log the error
    error_log("Awards upload error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    http_response_code(400);
    $response = ['error' => $e->getMessage()];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
} catch (Error $e) {
    // Catch PHP 7+ errors
    error_log("Awards upload PHP error: " . $e->getMessage() . " in " . $e->getFile() . " line " . $e->getLine());
    
    http_response_code(500);
    $response = ['error' => 'Internal server error occurred'];
    
    // Ensure clean JSON output
    ob_clean();
    echo json_encode($response);
    exit();
}


function performIconsAnalysis($rawText, $dataset) {
    // Preprocess input
    $text = strtolower($rawText);
    $textTokens = preprocessText($text);
    $textTokenSet = array_unique($textTokens);
    
    // Lightweight semantic map for boost
    $semanticMap = [
        'sustainability' => ['sustainable', 'environment', 'climate', 'green'],
        'international' => ['global', 'cross-border', 'overseas'],
        'collaboration' => ['partnership', 'partnerships', 'cooperation'],
        'leadership' => ['leader', 'leaders', 'mentorship'],
        'asean' => ['southeast asia', 'regional'],
        'mobility' => ['exchange', 'study abroad']
    ];
    
    $results = [];
    
    foreach ($dataset as $group) {
        $categoryLabel = $group['category'] ?? 'Institutional Awards';
        $awards = $group['awards'] ?? [];
        foreach ($awards as $award) {
            $title = $award['title'] ?? 'Unknown Award';
            $keywords = $award['keywords'] ?? [];
            
            // Tokenize award keywords
            $awardTokens = [];
            foreach ($keywords as $kw) {
                $awardTokens = array_merge($awardTokens, preprocessText(strtolower($kw)));
            }
            $awardTokens = array_unique($awardTokens);
            if (count($awardTokens) === 0) { continue; }
            
            // Jaccard similarity
            $intersection = array_intersect($textTokenSet, $awardTokens);
            $union = array_unique(array_merge($textTokenSet, $awardTokens));
            $similarity = count($union) > 0 ? (count($intersection) / count($union)) : 0.0;
            
            // Semantic boost
            $boost = 0.0;
            foreach ($semanticMap as $root => $rels) {
                if (strpos($text, $root) !== false) { $boost = max($boost, 0.1); }
                else {
                    foreach ($rels as $rel) {
                        if (strpos($text, $rel) !== false) { $boost = max($boost, 0.1); break; }
                    }
                }
            }
            
            // Category weights
            $weight = 1.0;
            $catLower = strtolower($categoryLabel);
            if (strpos($catLower, 'individual') !== false) { $weight = 1.1; }
            elseif (strpos($catLower, 'special') !== false) { $weight = 1.0; }
            else { $weight = 1.0; }
            
            $baseScore = min(1.0, $similarity + $boost);
            $weightedScore = min(1.0, $baseScore * $weight);
            
            // Thresholds
        $eligibility = 'Not Eligible';
        if ($weightedScore >= 0.90) { $eligibility = 'Eligible'; }
        elseif ($weightedScore >= 0.80) { $eligibility = 'Needs Attention'; }
            
            // Matched keywords (phrase presence)
            $matchedKeywords = [];
            foreach ($keywords as $kw) {
                if (stripos($text, $kw) !== false) { $matchedKeywords[] = $kw; }
            }
            
            $results[] = [
                'title' => $title,
                'category' => $categoryLabel,
                'score' => round($weightedScore * 100, 1),
                'status' => $eligibility,
                'matched_keywords' => $matchedKeywords
            ];
        }
    }
    
    // Sort by score desc
    usort($results, function($a, $b) { return ($b['score'] ?? 0) <=> ($a['score'] ?? 0); });
    return $results;
}

function preprocessText($text) {
    // Convert to lowercase, remove punctuation, split into words
    $text = strtolower($text);
    $text = preg_replace('/[^\w\s]/', ' ', $text);
    $words = preg_split('/\s+/', $text);
    
    // Remove common stopwords
    $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should'];
    $words = array_filter($words, function($word) use ($stopwords) {
        return !in_array($word, $stopwords) && strlen($word) > 2;
    });
    
    // Apply stemming and normalization
    $processedWords = [];
    foreach ($words as $word) {
        $stemmed = stemWord($word);
        if (strlen($stemmed) > 2) {
            $processedWords[] = $stemmed;
        }
    }
    
    return array_values($processedWords);
}

function stemWord($word) {
    // Remove common suffixes
    $suffixes = [
        'ing', 'ed', 'er', 'est', 'ly', 'tion', 'sion', 'ity', 'ness', 
        'ment', 'able', 'ible', 'al', 'ial', 'ic', 'ical', 'ous', 'ious',
        'ive', 'ative', 'itive', 's', 'es', 'ies', 'ism', 'ist', 'ize', 'ise'
    ];
    
    // Sort suffixes by length (longest first) to handle compound suffixes
    usort($suffixes, function($a, $b) {
        return strlen($b) - strlen($a);
    });
    
    $originalWord = $word;
    
    // Apply suffix removal
    foreach ($suffixes as $suffix) {
        if (strlen($word) > strlen($suffix) + 2 && substr($word, -strlen($suffix)) === $suffix) {
            $word = substr($word, 0, -strlen($suffix));
            break; // Only remove one suffix
        }
    }
    
    // Ensure the stemmed word is meaningful (at least 3 characters)
    return strlen($word) >= 3 ? $word : $originalWord;
}

function jaccardSimilarity($textTokens, $criteriaKeywords) {
    // Preprocess both sets with stemming
    $processedTextTokens = array_unique($textTokens);
    $processedCriteriaKeywords = array_unique($criteriaKeywords);
    
    // Apply stemming to criteria keywords as well
    $stemmedCriteria = [];
    foreach ($processedCriteriaKeywords as $keyword) {
        $stemmed = stemWord(strtolower($keyword));
        $stemmedCriteria[] = $stemmed;
    }
    $stemmedCriteria = array_unique($stemmedCriteria);
    
    // Handle compound keywords (e.g., "global citizenship" -> ["global", "citizenship"]) 
    $expandedCriteria = [];
    foreach ($stemmedCriteria as $keyword) {
        $expandedCriteria[] = $keyword;
        // Split compound words
        if (strpos($keyword, ' ') !== false) {
            $parts = explode(' ', $keyword);
            foreach ($parts as $part) {
                if (strlen($part) > 2) {
                    $expandedCriteria[] = $part;
                }
            }
        }
    }
    $expandedCriteria = array_unique($expandedCriteria);
    
    // Calculate exact matches
    $exactMatches = array_intersect($processedTextTokens, $expandedCriteria);
    
    // Calculate substring matches (partial matches)
    $partialMatches = calculateSubstringMatches($processedTextTokens, $expandedCriteria);
    
    // Combine exact and partial matches
    $allMatches = array_unique(array_merge($exactMatches, $partialMatches));
    
    // Calculate union (all unique tokens from both sets)
    $union = array_unique(array_merge($processedTextTokens, $expandedCriteria));
    
    if (count($union) === 0) {
        return 0;
    }
    
    // Enhanced scoring: exact matches get full weight, partial matches get 0.8 weight
    $exactScore = count($exactMatches);
    $partialScore = count($partialMatches) * 0.8;
    $totalMatches = $exactScore + $partialScore;
    
    // Boost score if we have multiple matches (indicates strong relevance)
    $matchRatio = count($allMatches) / count($union);
    if ($matchRatio > 0.1) { // If we have at least 10% matches
        $totalMatches *= 1.3; // 30% boost
    }
    
    return min($totalMatches / count($union), 1.0); // Cap at 1.0
}

function calculateSubstringMatches($textTokens, $criteriaKeywords) {
    $partialMatches = [];
    $minLength = 3; // Minimum length for substring matching
    
    foreach ($textTokens as $textToken) {
        if (strlen($textToken) < $minLength) continue;
        
        foreach ($criteriaKeywords as $criteriaKeyword) {
            if (strlen($criteriaKeyword) < $minLength) continue;
            
            // Check if one is a substring of the other (bidirectional)
            if (strpos($textToken, $criteriaKeyword) !== false || 
                strpos($criteriaKeyword, $textToken) !== false) {
                $partialMatches[] = $textToken;
                break; // Found a match, move to next text token
            }
            
            // Check for partial word overlap (at least 75% of shorter word)
            $shorterLen = min(strlen($textToken), strlen($criteriaKeyword));
            $longerLen = max(strlen($textToken), strlen($criteriaKeyword));
            
            if ($shorterLen >= 4 && ($shorterLen / $longerLen) >= 0.75) {
                // Calculate Levenshtein distance for fuzzy matching
                $distance = levenshtein($textToken, $criteriaKeyword);
                $maxDistance = max(1, intval($shorterLen * 0.25)); // Allow up to 25% difference
                
                if ($distance <= $maxDistance) {
                    $partialMatches[] = $textToken;
                    break;
                }
            }
        }
    }
    
    return array_unique($partialMatches);
}

function generateIconsRecommendation($awardName, $eligibility, $criteriaChecklist, $percentCriteria) {
    $met = array_values(array_filter($criteriaChecklist, function($c) { return !empty($c['met']); }));
    $unmet = array_values(array_filter($criteriaChecklist, function($c) { return empty($c['met']); }));
    $metTexts = array_map(function($c){ return $c['text']; }, $met);
    $unmetTexts = array_map(function($c){ return $c['text']; }, $unmet);
    
    if ($eligibility === 'Eligible') {
        return 'Strong alignment with ' . $awardName . '. Evidence for: ' . implode(', ', array_slice($metTexts, 0, 3)) . '.';
    }
    if ($eligibility === 'Partially Eligible') {
        return 'Partial alignment. Strengthen: ' . implode(', ', array_slice($unmetTexts, 0, 3)) . '.';
    }
    return 'Not eligible yet. Include initiatives related to: ' . implode(', ', array_slice($unmetTexts, 0, 3)) . '.';
}

function phraseMatches($text, $textTokens, $phrase, $phraseTokens) {
    // Exact phrase presence quick check
    if (strpos($text, $phrase) !== false) { return true; }
    
    // Token overlap ratio
    if (empty($phraseTokens)) { return false; }
    $intersection = array_intersect($phraseTokens, $textTokens);
    $overlapRatio = count($intersection) / count($phraseTokens);
    if ($overlapRatio >= 0.7) { return true; }
    
    // Fuzzy substring: if any token of phrase appears as substring of any text token
    foreach ($phraseTokens as $pt) {
        foreach ($textTokens as $tt) {
            if (strlen($pt) >= 4 && strlen($tt) >= 4) {
                if (strpos($tt, $pt) !== false || strpos($pt, $tt) !== false) {
                    return true;
                }
            }
        }
    }
    return false;
}

function generateRecommendations($analysisResults) {
    $recommendations = [];
    foreach ($analysisResults as $result) {
        $recommendations[] = $result['recommendation'];
    }
    return implode(' ', $recommendations);
}


function simulateOCRExtraction($filename) {
    $filename = strtolower($filename);
    
    // Simulate different types of award documents based on filename
    if (strpos($filename, 'citizenship') !== false || strpos($filename, 'global') !== false) {
        return "Global Citizenship Award Certificate - This document recognizes outstanding contributions to global citizenship education, intercultural understanding, and sustainable development goals. The recipient has demonstrated exceptional commitment to promoting diversity, inclusivity, and responsible leadership among students and faculty.";
    } elseif (strpos($filename, 'international') !== false || strpos($filename, 'education') !== false) {
        return "Outstanding International Education Program Award - This award celebrates innovative international education programs that promote global access, collaborative innovation, and inclusive internationalization. The program demonstrates excellence in student exchange, cultural experience, and international partnerships.";
    } elseif (strpos($filename, 'leadership') !== false || strpos($filename, 'emerging') !== false) {
        return "Emerging Leadership Award - This recognition honors rising leaders in internationalization who have shown exceptional innovation, mentorship, and strategic growth. The recipient demonstrates outstanding leadership development, collaboration, and capacity building in international education.";
    } elseif (strpos($filename, 'executive') !== false || strpos($filename, 'senior') !== false) {
        return "Internationalization Leadership Award - This prestigious award recognizes executive leadership in internationalization, bold innovation, and lifelong learning. The recipient has demonstrated purposeful leadership, ethical decision-making, and inclusive education practices.";
    } elseif (strpos($filename, 'regional') !== false || strpos($filename, 'office') !== false) {
        return "Best Regional Office for Internationalization Award - This award recognizes regional offices that have achieved measurable impact through collaboration, sustainability, and impactful initiatives. The office demonstrates excellence in faculty exchange, student enrollment, and regional partnerships.";
    } elseif (strpos($filename, 'sustainability') !== false || strpos($filename, 'green') !== false) {
        return "Sustainability Award Certificate - This document recognizes exceptional commitment to environmental sustainability and green campus initiatives. The institution has implemented comprehensive sustainability programs, renewable energy projects, waste reduction strategies, and climate action plans that demonstrate leadership in environmental stewardship.";
    } elseif (strpos($filename, 'asean') !== false || strpos($filename, 'regional') !== false) {
        return "ASEAN Awareness Initiative Award - This award celebrates outstanding contributions to ASEAN regional cooperation and cultural understanding. The recipient has developed innovative programs that strengthen ASEAN identity, promote regional solidarity, and enhance cross-border collaboration among Southeast Asian nations.";
    } elseif (strpos($filename, 'iro') !== false || strpos($filename, 'community') !== false) {
        return "IRO Community Excellence Award - This recognition honors International Relations Offices that have demonstrated exceptional collaboration and innovation. The community has fostered strong partnerships, shared resources, and implemented joint initiatives that advance internationalization across multiple institutions.";
    } elseif (strpos($filename, 'ched') !== false || strpos($filename, 'policy') !== false) {
        return "CHED Regional Office Excellence Award - This award recognizes outstanding administrative leadership in promoting internationalization policies and coordination. The regional office has successfully implemented innovative programs, supported institutional partnerships, and facilitated regional cooperation in higher education.";
    } else {
        // Generate unique content based on filename hash to ensure different results
        $hash = crc32($filename);
        $variants = [
            "Award Certificate - This document recognizes outstanding achievements in international education and global engagement. The recipient has demonstrated excellence in leadership, innovation, collaboration, and inclusive practices. This award celebrates contributions to internationalization, diversity, equity, and sustainable development goals.",
            "Institutional Excellence Award - This recognition celebrates exceptional achievements in higher education internationalization. The institution has demonstrated remarkable progress in student mobility programs, faculty exchange initiatives, and cross-cultural learning opportunities that enhance global competence.",
            "Academic Partnership Award - This award honors outstanding collaboration between institutions that have created innovative international programs. The partnership has resulted in significant student exchanges, joint research projects, and cultural exchange programs that benefit both institutions.",
            "Global Innovation Award - This certificate recognizes groundbreaking initiatives in international education that have transformed learning experiences. The recipient has developed cutting-edge programs that integrate global perspectives, sustainable practices, and inclusive methodologies.",
            "Leadership Excellence Award - This document celebrates exceptional leadership in advancing international education goals. The recipient has demonstrated visionary leadership, strategic planning, and successful implementation of comprehensive internationalization strategies."
        ];
        
        $variantIndex = $hash % count($variants);
        return $variants[$variantIndex];
    }
}
?>
