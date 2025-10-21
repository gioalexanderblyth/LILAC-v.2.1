🧩 Main Goal

The algorithm determines whether an uploaded award certificate qualifies the organization (or individual) for a specific academic or institutional award based on how closely the certificate’s data matches the award’s criteria.

⚙️ Algorithm Logic (Simplified Version)
1. Input Data

Award criteria — stored in the database (e.g., keywords, year, type, category, institution, etc.)

Uploaded award certificate — when uploaded, the system extracts metadata (like award name, category, description, and other text data).

2. Similarity Checking

For each uploaded certificate, the system performs a textual similarity check between:

The certificate’s extracted content, and

Each award’s defined criteria in the database.

👉 The system may use Jaccard Similarity, Cosine Similarity, or a keyword matching function.

3. Compute Similarity Score

Each comparison produces a percentage match (0–100%), showing how close the certificate is to meeting the award’s criteria.

Example:

Certificate	Award Criteria	Similarity Score
“Sustainability Award 2024”	“Environmental Sustainability Award”	92%
“Leadership in Education”	“Outstanding Leadership Award”	78%
4. Determine Eligibility

Once a percentage score is computed, the algorithm applies your new threshold rule:

Score (%)	Eligibility Result
90–100%	✅ Eligible
70–89%	⚠️ Almost Eligible
Below 70%	❌ Not Eligible
5. Output / Display

The system displays the results in the Awards Page table:

The name of the uploaded certificate,

The award title it was compared to,

The percentage match, and

The eligibility label (“Eligible”, “Almost Eligible”, or “Not Eligible”).

🔍 Example Simulation

Uploaded Certificate:

“Sustainability and Environmental Excellence Award 2024”

Award in Database:

“Environmental Sustainability Award”

Similarity Computed: 0.91 → 91%

✅ Result: Eligible

💡 Summary of Simplified Logic

User uploads an award certificate.

System extracts text (title, description).

System compares text against stored award criteria.

System calculates similarity percentage.

Based on the percentage, it classifies eligibility:

≥ 90 → Eligible

70–89 → Almost Eligible

< 70 → Not Eligible