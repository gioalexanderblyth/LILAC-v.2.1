🏆 ICONS 2025 — Core 4 Awards Logic

These are the main Institutional and Individual awards your system evaluates text against.

🏫 1. Global Citizenship Award

Goal: Identify programs that foster intercultural understanding, global engagement, and active citizenship.

Key criteria keywords:

global citizenship

intercultural understanding

community engagement

student mobility

responsible leadership

global collaboration

cross-cultural activities

changemaker development

Weight: Institutional — 1.0x

Thresholds:

≥ 80% → Eligible

60–79% → Partially Eligible

< 60% → Not Eligible

🌏 2. Outstanding International Education Program Award

Goal: Detect initiatives that promote internationalization in academics, inclusive global opportunities, and cross-border education.

Key criteria keywords:

international education

cross-border collaboration

global partnerships

academic mobility

exchange programs

inclusivity

innovation

collaborative projects

Weight: Institutional — 1.0x

Thresholds:

≥ 80% → Eligible

60–79% → Partially Eligible

< 60% → Not Eligible

🌿 3. Sustainability Award

Goal: Recognize efforts that promote sustainability, environmental awareness, and long-term commitment to green initiatives.

Key criteria keywords:

sustainability

environmental awareness

green initiative

eco-friendly practices

carbon reduction

long-term sustainability program

community sustainability outreach

Weight: Institutional — 1.0x

Thresholds:

≥ 80% → Eligible

60–79% → Partially Eligible

< 60% → Not Eligible

👥 4. Internationalization Leadership Award

Goal: Identify strong leadership and governance driving internationalization strategies within HEIs.

Key criteria keywords:

internationalization leadership

institutional leadership

strategic vision

governance

mentorship

ethical leadership

collaboration excellence

capacity building

Weight: Individual — 1.1x

Thresholds:

≥ 80% → Eligible

60–79% → Partially Eligible

< 60% → Not Eligible

⚙️ Algorithm (Jaccard + Semantic Scoring Hybrid)
Step 1: Preprocessing

Convert both award text and award criteria keywords into lowercase.

Remove stopwords (“the”, “of”, “and”, etc.).

Tokenize into word sets.

Step 2: Jaccard Similarity

Compute overlap ratio between the uploaded document and each award’s keyword set.

𝐽
(
𝐴
,
𝐵
)
=
∣
𝐴
∩
𝐵
∣
∣
𝐴
∪
𝐵
∣
J(A,B)=
∣A∪B∣
∣A∩B∣
	​


Where:

A = set of unique words from uploaded text

B = set of unique keywords for the award

Example:

A = {international, partnership, students, research, ASEAN}
B = {international, collaboration, program, education}
J = 2 / 7 = 0.2857 → 28.57%

Step 3: Semantic Synonym Scoring

Boost scores using synonym mapping and semantic similarity (via a lightweight NLP model or a synonyms.json file).

For example:

"partnership" ≈ "collaboration"
"mobility" ≈ "exchange"
"leadership" ≈ "governance"


This adds +10–20% to the match if strong synonyms are found.

Step 4: Weighted Aggregation

Combine base Jaccard score + semantic boost × type multiplier:

final_score = (jaccard_score + semantic_bonus) × type_weight

Step 5: Eligibility Classification
if final_score ≥ 80 → Eligible ✅
elif final_score ≥ 60 → Partially Eligible ⚠️
else → Not Eligible ❌

Step 6: Output Format

Each award should display like this:

🏅 Internationalization Leadership Award
Type: Individual
Score: 92%
✅ Eligible
Criteria Matched:
- leadership excellence
- internationalization leadership
- institutional leadership
