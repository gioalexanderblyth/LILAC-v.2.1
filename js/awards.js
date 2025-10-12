// Awards frontend logic: upload -> OCR -> classify -> render -> report
(function() {
    // Check if running on correct server
    if (location.protocol === 'file:') {
        alert('⚠️ Server Required\n\nThis page must be served from a web server to work properly.\n\nPlease use the start-server.bat file to start the PHP server, then access the page at:\nhttp://localhost:8080/awards.html');
        return;
    }
    
    const $ = (sel) => document.querySelector(sel);

    async function ocrFile(file) {
        if (!file) return '';
        
        // Simple fallback: return text based on filename patterns
        // Server-side OCR is handled in the upload API
        const filename = file.name.toLowerCase();
        if (filename.includes('citizenship') || filename.includes('global')) {
            return "Global citizenship award document with intercultural understanding and sustainability initiatives.";
        } else if (filename.includes('international') || filename.includes('education')) {
            return "International education program with global partnerships and student exchange programs.";
        } else if (filename.includes('leadership') || filename.includes('emerging')) {
            return "Leadership development program with mentorship and strategic growth initiatives.";
        }
        
        return "Award document recognizing excellence in international education and global engagement.";
    }

    function buildFormData({ file, fields }) {
        const fd = new FormData();
        if (file) fd.append('file', file);
        Object.entries(fields).forEach(([k,v]) => fd.append(k, typeof v === 'object' ? JSON.stringify(v) : v));
        return fd;
    }

    function authHeaders() {
        let user = null;
        try { user = JSON.parse(localStorage.getItem('lilac_user') || sessionStorage.getItem('lilac_user') || 'null'); } catch {}
        const headers = {};
        if (user) { headers['X-User'] = user.username; headers['X-Role'] = user.role || 'user'; }
        return headers;
    }

    async function uploadAndClassify({ file, title, date, description, meta, ocrText }) {
        const fd = buildFormData({ file, fields: { title, date, description, meta } });
        console.log('Uploading to: api/awards-upload.php');
        console.log('File:', file.name, 'Size:', file.size);
        
        const res = await fetch('api/awards-upload.php', { method: 'POST', body: fd, headers: authHeaders() });
        console.log('Response status:', res.status);
        
        if (!res.ok) {
            const errorText = await res.text();
            console.error('Server response:', errorText);
            
            // Check if response looks like PHP code instead of JSON
            if (errorText.trim().startsWith('<?php')) {
                throw new Error('Server Error: PHP files not being processed. Please use the PHP server at http://localhost:8080');
            }
            
            let error;
            try {
                error = JSON.parse(errorText);
            } catch {
                error = { error: 'Upload failed: ' + errorText };
            }
            throw new Error(error.error || 'Upload failed');
        }
        const result = await res.json();
        console.log('Upload result:', result);
        return result;
    }

    function renderAnalysis(result) {
        // Display results in the analysis table
        const resultsTableBody = document.getElementById('resultsTableBody');
        const analysisResults = document.getElementById('analysisResults');
        
        if (!resultsTableBody || !analysisResults) return;
        
        resultsTableBody.innerHTML = '';
        
        if (result.analysis && Array.isArray(result.analysis)) {
            result.analysis.forEach(analysis => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50 dark:hover:bg-gray-800';
                
                const statusIcon = getStatusIcon(analysis.status);
                const scoreColor = getScoreColor(analysis.score);
                
                row.innerHTML = `
                    <td class="border border-border-light dark:border-border-dark px-3 py-2 text-sm text-text-light dark:text-text-dark">${analysis.category}</td>
                    <td class="border border-border-light dark:border-border-dark px-3 py-2 text-sm">
                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${scoreColor}">
                            ${(analysis.score * 100).toFixed(1)}%
                        </span>
                    </td>
                    <td class="border border-border-light dark:border-border-dark px-3 py-2 text-sm">
                        <span class="inline-flex items-center gap-1">
                            ${statusIcon}
                            <span class="text-text-light dark:text-text-dark">${analysis.status}</span>
                        </span>
                    </td>
                    <td class="border border-border-light dark:border-border-dark px-3 py-2 text-sm text-text-muted-light dark:text-text-muted-dark">${analysis.recommendation}</td>
                `;
                
                resultsTableBody.appendChild(row);
            });
            
            analysisResults.classList.remove('hidden');
        }
        
        // Update counters
        if (result.counters) {
            updateAwardCounters(result.counters);
        }
        
        // Show success message
        showToast(`Analysis completed! Found ${result.matched_categories ? result.matched_categories.length : 0} matching awards.`, 'success');
    }
    
    function getStatusIcon(status) {
        switch (status) {
            case 'Eligible':
                return '<span class="text-green-500">✅</span>';
            case 'Partial Match':
                return '<span class="text-yellow-500">⚠️</span>';
            case 'Not Eligible':
                return '<span class="text-red-500">❌</span>';
            default:
                return '<span class="text-gray-500">❓</span>';
        }
    }
    
    function getScoreColor(score) {
        if (score >= 0.5) return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        if (score >= 0.25) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200';
        return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
    }
    
    function updateAwardCounters(counters) {
        Object.entries(counters).forEach(([awardName, count]) => {
            const counterId = getCounterId(awardName);
            const counterElement = document.getElementById(counterId);
            if (counterElement) {
                counterElement.textContent = count;
            }
        });
    }
    
    function getCounterId(awardName) {
        const mapping = {
            'Global Citizenship Award': 'global-citizenship-count',
            'Outstanding International Education Program Award': 'outstanding-international-count',
            'Emerging Leadership Award': 'emerging-leadership-count',
            'Internationalization Leadership Award': 'internationalization-leadership-count',
            'Best Regional Office for Internationalization Award': 'best-regional-office-count'
        };
        return mapping[awardName] || '';
    }
    
    function showToast(message, type = 'success') {
        const successToast = document.getElementById('successToast');
        const toastMessage = document.getElementById('toastMessage');
        
        if (!successToast || !toastMessage) return;
        
        toastMessage.textContent = message;
        successToast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2 ${
            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
        }`;
        successToast.classList.remove('hidden');
        
        setTimeout(() => {
            successToast.classList.add('hidden');
        }, 5000);
    }

    async function openOverride() {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/40 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white dark:bg-card-dark rounded p-4 w-full max-w-lg">
                <h3 class="text-lg font-bold mb-2">Manual Override</h3>
                <label class="block text-sm mb-1">Final Category</label>
                <select id="ovr-cat" class="w-full border rounded p-2 mb-3">
                    <option>Global Citizenship Award</option>
                    <option>Outstanding International Education Program</option>
                    <option>Emerging Leadership Award</option>
                    <option>Internationalization Leadership Award</option>
                    <option>Best Regional Office for Internationalization</option>
                </select>
                <label class="block text-sm mb-1">Recommendations</label>
                <textarea id="ovr-recs" class="w-full border rounded p-2 h-24"></textarea>
                <div class="flex justify-end gap-2 mt-3">
                    <button id="ovr-cancel" class="px-3 py-1.5 rounded border">Cancel</button>
                    <button id="ovr-save" class="px-3 py-1.5 rounded bg-primary text-white">Save</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
        modal.querySelector('#ovr-cancel').onclick = ()=> document.body.removeChild(modal);
        modal.querySelector('#ovr-save').onclick = async ()=> {
            const payload = {
                award_id: await latestAwardId(),
                final_category: modal.querySelector('#ovr-cat').value,
                checklist: {},
                recommendations: modal.querySelector('#ovr-recs').value
            };
            await fetch('api/awards.php?action=override', { method:'POST', headers:Object.assign({'Content-Type':'application/json'}, authHeaders()), body: JSON.stringify(payload)});
            document.body.removeChild(modal);
            refreshStats();
        };
    }

    async function openLinkEvent() {
        const res = await fetch('api/events.php?action=list-minimal');
        const events = res.ok ? await res.json() : [];
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black/40 flex items-center justify-center z-50';
        const options = events.map(e => `<option value="${e.id}">${e.title} — ${e.date}</option>`).join('');
        modal.innerHTML = `
            <div class="bg-white dark:bg-card-dark rounded p-4 w-full max-w-lg">
                <h3 class="text-lg font-bold mb-2">Link Event</h3>
                <select id="lnk-event" class="w-full border rounded p-2 mb-3">${options}</select>
                <div class="flex justify-end gap-2 mt-3">
                    <button id="lnk-cancel" class="px-3 py-1.5 rounded border">Cancel</button>
                    <button id="lnk-save" class="px-3 py-1.5 rounded bg-primary text-white">Link</button>
                </div>
            </div>`;
        document.body.appendChild(modal);
        modal.querySelector('#lnk-cancel').onclick = ()=> document.body.removeChild(modal);
        modal.querySelector('#lnk-save').onclick = async ()=> {
            const payload = { award_id: await latestAwardId(), event_id: parseInt(modal.querySelector('#lnk-event').value, 10) };
            await fetch('api/awards.php?action=link-event', { method:'POST', headers:Object.assign({'Content-Type':'application/json'}, authHeaders()), body: JSON.stringify(payload)});
            document.body.removeChild(modal);
        };
    }

    async function latestAwardId() {
        // naive: fetch list and take most recent
        try {
            const res = await fetch('api/awards.php?action=list');
            if (!res.ok) return 0;
            const list = await res.json();
            return list.length ? list[0].id : 0;
        } catch { return 0; }
    }

    async function downloadReport(container) {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({ orientation: 'p', unit: 'pt', format: 'a4' });
        const node = container.cloneNode(true);
        node.style.width = '800px';
        document.body.appendChild(node);
        const canvas = await html2canvas(node, { scale: 2 });
        document.body.removeChild(node);
        const imgData = canvas.toDataURL('image/png');
        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const ratio = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
        const w = canvas.width * ratio;
        const h = canvas.height * ratio;
        pdf.addImage(imgData, 'PNG', (pageWidth - w)/2, 40, w, h);
        pdf.save('award-analysis.pdf');
    }

    async function initUpload() {
        // Attach to existing upload control in header
        const input = document.getElementById('file-upload');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadProgress = document.getElementById('upload-progress-section');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        
        if (!input) return;
        
        input.accept = '.pdf,.png,.jpg,.jpeg,.docx';
        
        // Upload button click handler
        if (uploadBtn) {
            uploadBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                input.click();
            });
        }
        
        input.addEventListener('change', handleFileUpload);
        
        async function handleFileUpload() {
            const file = input.files && input.files[0];
            if (!file) return;
            
            // Validate file type
            const allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showToast('Invalid file type. Please upload PDF, DOCX, JPG, or PNG files.', 'error');
                return;
            }
            
            // Show progress section only when processing starts
            if (uploadProgress) {
                uploadProgress.classList.remove('hidden');
                if (progressBar) progressBar.style.width = '0%';
                if (progressText) progressText.textContent = 'Uploading file...';
            }
            
            try {
                // Update progress
                if (progressBar) progressBar.style.width = '30%';
                if (progressText) progressText.textContent = 'Processing document...';
                
                // Basic meta form - can be extended later
                const title = file.name.replace(/\.[^/.]+$/, "");
                const date = new Date().toISOString().slice(0,10);
                const description = 'Uploaded via awards form';
                const meta = {};
                
                // OCR (now handled server-side)
                if (progressBar) progressBar.style.width = '60%';
                if (progressText) progressText.textContent = 'Analyzing content...';
                
                // Upload + classify
                const res = await uploadAndClassify({ file, title, date, description, meta, ocrText: '' });
                
                if (res && res.success) {
                    if (progressBar) progressBar.style.width = '100%';
                    if (progressText) progressText.textContent = 'Analysis complete!';
                    
                    renderAnalysis(res);
                    refreshStats();
                    
                    // Refresh award list data if the function exists
                    if (typeof loadAwardListData === 'function') {
                        loadAwardListData();
                    }
                    
                    // Hide progress after delay
                    setTimeout(() => {
                        if (uploadProgress) uploadProgress.classList.add('hidden');
                        if (progressBar) progressBar.style.width = '0%';
                        if (progressText) progressText.textContent = 'Processing... Analyzing document with OCR.';
                    }, 2000);
                } else {
                    throw new Error(res.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showToast('Upload failed: ' + error.message, 'error');
                if (uploadProgress) uploadProgress.classList.add('hidden');
                if (progressBar) progressBar.style.width = '0%';
                if (progressText) progressText.textContent = 'Processing... Analyzing document with OCR.';
            }
        }
    }

    async function refreshStats() {
        try {
            const res = await fetch('api/awards-stats.php');
            if (!res.ok) {
                console.error('Failed to fetch stats, status:', res.status);
                const errorText = await res.text();
                console.error('Error response:', errorText);
                return;
            }
            const responseText = await res.text();
            console.log('Raw response:', responseText);
            
            // Check if response looks like PHP code instead of JSON
            if (responseText.trim().startsWith('<?php')) {
                console.error('Server returned PHP code instead of JSON. Make sure you are accessing the page via HTTP server, not file:// protocol.');
                showToast('Server Error: PHP files not being processed. Please use the PHP server.', 'error');
                return;
            }
            
            const data = JSON.parse(responseText);
            if (data.success && data.counters) {
                updateAwardCounters(data.counters);
            }
        } catch (error) {
            console.error('Failed to load award counters:', error);
            if (error instanceof SyntaxError && error.message.includes('Unexpected token')) {
                showToast('Server Error: Invalid JSON response. Please check server configuration.', 'error');
            }
        }
    }

    async function runAIAnalysis() {
        try {
            // Get current award data
            const res = await fetch('api/awards.php?action=stats');
            const stats = res.ok ? await res.json() : [];
            
            const res2 = await fetch('api/awards.php?action=list');
            const awards = res2.ok ? await res2.json() : [];
            
            // Generate AI insights based on current data
            const insights = generateAIInsights(stats, awards);
            renderAIInsights(insights);
        } catch (error) {
            console.error('AI Analysis failed:', error);
        }
    }

    function generateAIInsights(stats, awards) {
        const insights = [];
        
        // Analyze strengths
        const strengths = stats.filter(s => s.count > 0);
        if (strengths.length > 0) {
            insights.push({
                type: 'strength',
                title: 'Current Strengths',
                content: `You have strong foundations in: ${strengths.map(s => s.category).join(', ')}. These areas show good progress toward ICONS 2024 recognition.`,
                icon: 'trending_up',
                color: 'green'
            });
        }
        
        // Identify gaps
        const allCategories = ['Global Citizenship Award', 'Outstanding International Education Program', 'Emerging Leadership Award', 'Internationalization Leadership Award', 'Best Regional Office for Internationalization'];
        const missingCategories = allCategories.filter(cat => !stats.some(s => s.category === cat));
        if (missingCategories.length > 0) {
            insights.push({
                type: 'opportunity',
                title: 'Growth Opportunities',
                content: `Consider focusing on: ${missingCategories.join(', ')}. These areas represent untapped potential for ICONS 2024 awards.`,
                icon: 'lightbulb',
                color: 'purple'
            });
        }
        
        // Strategic recommendations
        if (awards.length === 0) {
            insights.push({
                type: 'action',
                title: 'Getting Started',
                content: 'Upload your first award document to begin AI-powered analysis. The system will provide personalized recommendations based on ICONS 2024 criteria.',
                icon: 'rocket_launch',
                color: 'blue'
            });
        } else {
            insights.push({
                type: 'action',
                title: 'Next Steps',
                content: `With ${awards.length} award${awards.length > 1 ? 's' : ''} analyzed, focus on strengthening areas with lower Jaccard similarity scores and document more evidence for missing criteria.`,
                icon: 'psychology',
                color: 'indigo'
            });
        }
        
        return insights;
    }

    function renderAIInsights(insights) {
        const container = document.getElementById('ai-insights');
        if (!container) return;
        
        container.innerHTML = insights.map(insight => `
            <div class="p-4 bg-gradient-to-r from-${insight.color}-50 to-${insight.color}-100 dark:from-${insight.color}-900/20 dark:to-${insight.color}-800/20 rounded-lg border border-${insight.color}-200 dark:border-${insight.color}-800">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-${insight.color}-600 dark:text-${insight.color}-400">${insight.icon}</span>
                    <div>
                        <h4 class="font-semibold text-${insight.color}-900 dark:text-${insight.color}-200">${insight.title}</h4>
                        <p class="text-sm text-${insight.color}-700 dark:text-${insight.color}-300 mt-1">${insight.content}</p>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function initAI() {
        const btn = document.getElementById('btn-ai-analyze');
        if (btn) {
            btn.addEventListener('click', runAIAnalysis);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        initUpload();
        initAI();
        refreshStats();
    });
})();


