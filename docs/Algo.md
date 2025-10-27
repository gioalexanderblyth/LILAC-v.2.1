🧠 How the Algorithm Works (Step-by-Step)

Our system uses a Weighted Keyword Matching Algorithm (an improved version of the Jaccard logic) to determine how closely an uploaded award certificate matches any of the ICONS 2025 Award criteria.

It doesn’t rely on long paragraphs or many keywords — instead, it focuses on key thematic words related to each award.

⚙️ 1. The System Receives an Uploaded Certificate

When the user uploads a certificate (e.g., “Sustainability Award – Central Philippine University”), the system:

Extracts the text content using OCR (Optical Character Recognition).

Converts the text to lowercase for uniform comparison.

Removes symbols, extra spaces, etc.

📋 2. Load the Award Criteria

The system has a list of all awards and their important keywords and weights.

For example:

Award Name	Keywords	Weight
Sustainability Award	sustainability, sustainable, eco, green, environment	50
Global Citizenship Award	global, citizenship, international, intercultural	50
Outstanding International Education Program	education, international, exchange, program	50
ASEAN Awareness Initiative Award	ASEAN, awareness, regional, solidarity	50
Emerging Leadership Award	emerging, leader, innovation, initiative	50
Internationalization Leadership Award	leadership, internationalization, strategic, vision	50
Best CHED Regional Office for IZN	CHED, regional, internationalization, office	50
Most Promising Regional IRO Community	IRO, regional, promising, network, collaboration	50

Each keyword or phrase is tied to a specific weight, representing its importance for matching.

🔍 3. Keyword Detection and Scoring

The algorithm scans the extracted certificate text for any matching keywords from each award.

Every time it finds a match:

It adds the keyword’s weight value to the total score for that award.

Then it calculates a match percentage like this:

\text{Match %} = \frac{\text{Total Matched Keyword Weight}}{\text{Total Possible Weight}} \times 100
🧩 4. Apply the Eligibility Rules

After computing the match percentage, the system checks your rule:

Match %	Status
90% and above	✅ Eligible
70%–89%	🟡 Almost Eligible
Below 70%	❌ Not Eligible
🧠 5. Generate the Result

Finally, the system displays in your Analysis & Recommendations panel:

The match confidence percentage (e.g., 92.3%)

The eligibility status (“Eligible”, “Almost Eligible”, or “Not Eligible”)

The award name(s) that the certificate most closely matches

Optional: a short explanation or recommended next step

Example Output:

Award: Sustainability Award
Match Confidence: 92.5%
Status: Eligible
Recommendation: This certificate aligns strongly with sustainability and environmental impact themes. Consider submitting for the Sustainability Award.

🎯 How It Solves the 3.1% Problem

Before, the system only counted shared words, which made short certificates score very low.
Now, even if the text only says “Sustainability Award – CPU”, the keyword “sustainability” alone gives enough weight (e.g., 80%) to classify it as Almost Eligible or Eligible, depending on how the weights are tuned.

So you get realistic results even with short documents like award certificates.

💬 Summary
Step	Description
1	Upload a certificate
2	System extracts and cleans text
3	System loads each award’s weighted keywords
4	Algorithm matches keywords and computes score
5	Match % is classified as Eligible, Almost Eligible, or Not Eligible
6	Displays results in Analysis & Recommendations

Criteria will be based on this Link: https://ieducationphl.ched.gov.ph/asia-pacific/