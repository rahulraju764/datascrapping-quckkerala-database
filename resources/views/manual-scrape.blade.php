<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual HTML Scraper - Quickerala</title>
    <!-- Modern Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; }
        .glass { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.1); }
        .scrollbar::-webkit-scrollbar { width: 6px; }
        .scrollbar::-webkit-scrollbar-track { background: transparent; }
        .scrollbar::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.2); border-radius: 10px; }
        .spinner { border: 4px solid rgba(255, 255, 255, 0.1); border-left-color: #3b82f6; border-radius: 50%; width: 24px; height: 24px; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen py-10 px-4">
    <div class="max-w-6xl mx-auto space-y-8">
        <!-- Header -->
        <div class="space-y-2">
            <h1 class="text-4xl font-extrabold text-blue-500">Manual Scraper</h1>
            <p class="text-slate-400">Targeting: <span class="italic text-slate-300">class="list-card d-grid rounded-10 hover-underline"</span></p>
        </div>

        <!-- Controls -->
        <div class="glass p-6 rounded-2xl shadow-2xl space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-8">
                    <label class="block text-sm font-medium text-slate-400 mb-2">Target URL</label>
                    <input type="text" id="targetUrl" value="https://www.quickerala.com/hotels-restaurants/ct-412"
                        class="w-full bg-slate-800 border-slate-700 text-white rounded-lg px-4 py-3 focus:ring-2 focus:ring-blue-500 transition-all outline-none">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-400 mb-2">Max Scrolls</label>
                    <select id="maxScrolls" class="w-full bg-slate-800 border-slate-700 text-white rounded-lg px-4 py-3 outline-none">
                        <option value="1">1 Scroll</option>
                        <option value="5">5 Scrolls</option>
                        <option value="10">10 Scrolls</option>
                        <option value="20" selected>20 Scrolls</option>
                        <option value="50">50 Scrolls</option>
                        <option value="100">100 Scrolls</option>
                        <option value="200">200 Scrolls</option>
                    </select>
                </div>
                <div class="md:col-span-1">
                    <label class="block text-sm font-medium text-slate-400 mb-2">Max Pages</label>
                    <select id="maxPages" class="w-full bg-slate-800 border-slate-700 text-white rounded-lg px-2 py-3 outline-none">
                        <option value="1" selected>1</option>
                        <option value="3">3</option>
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                    </select>
                </div>
                <div class="md:col-span-1 flex items-end">
                    <button id="startScrape" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-all shadow-lg hover:shadow-blue-500/20 disabled:opacity-50 disabled:cursor-not-allowed">
                        Go
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Logging Console -->
            <div class="glass rounded-2xl overflow-hidden flex flex-col h-[600px]">
                <div class="px-6 py-4 border-b border-slate-700 bg-slate-800/50 flex justify-between items-center">
                    <h2 class="font-semibold text-slate-200 uppercase tracking-widest text-xs">Run Logs</h2>
                    <div id="statusIndicator" class="flex items-center gap-2 text-xs text-slate-400">
                        <div id="statusDot" class="w-2 h-2 rounded-full bg-slate-600"></div>
                        <span id="statusText">Idle</span>
                    </div>
                </div>
                <div id="logConsole" class="flex-1 p-6 font-mono text-sm space-y-2 overflow-y-auto scrollbar bg-black/30 text-emerald-400">
                    <div class="text-slate-500">> Ready for manual scrape...</div>
                </div>
            </div>

            <!-- Extraction Preview -->
            <div class="glass rounded-2xl overflow-hidden flex flex-col h-[600px]">
                <div class="px-6 py-4 border-b border-slate-700 bg-slate-800/50 flex justify-between items-center">
                    <h2 class="font-semibold text-slate-200 uppercase tracking-widest text-xs">Extracted Results</h2>
                    <span id="countBadge" class="bg-blue-500/20 text-blue-400 px-2 py-1 rounded text-xs font-bold">0 Found</span>
                </div>
                <div id="resultsList" class="flex-1 p-6 space-y-4 overflow-y-auto scrollbar bg-slate-900/40">
                    <div class="text-slate-600 text-center py-20 italic">No data extracted yet.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden HTML Output (all content) -->
    <div id="hiddenLog" class="hidden"></div>

    <script>
        const startBtn = document.getElementById('startScrape');
        const logConsole = document.getElementById('logConsole');
        const resultsList = document.getElementById('resultsList');
        const countBadge = document.getElementById('countBadge');
        const statusText = document.getElementById('statusText');
        const statusDot = document.getElementById('statusDot');

        function appendLog(text, type = 'info') {
            const div = document.createElement('div');
            const timestamp = new Date().toLocaleTimeString();
            div.className = type === 'error' ? 'text-red-400' : 'text-emerald-400';
            div.innerHTML = `<span class="text-slate-500">[${timestamp}]</span> ${text}`;
            logConsole.appendChild(div);
            logConsole.scrollTop = logConsole.scrollHeight;
        }

        async function runScrape() {
            const baseUrl = document.getElementById('targetUrl').value;
            const maxScrolls = document.getElementById('maxScrolls').value;
            const maxPages = parseInt(document.getElementById('maxPages').value);

            // UI Reset
            startBtn.disabled = true;
            statusText.innerText = 'Starting Batch...';
            statusDot.classList.replace('bg-slate-600', 'bg-blue-500');
            statusDot.classList.add('animate-pulse');
            logConsole.innerHTML = '<div class="text-slate-500">> Initiating batch process...</div>';
            resultsList.innerHTML = '';
            countBadge.innerText = '0 Found';
            
            let totalFound = 0;

            for (let p = 1; p <= maxPages; p++) {
                // Construct paginated URL
                let currentUrl = baseUrl;
                
                if (currentUrl.includes('page=')) {
                    // Update existing page=N query param
                    currentUrl = currentUrl.replace(/page=\d+/, `page=${p}`);
                } else if (currentUrl.includes('/p-')) {
                    // Update existing /p-N segment
                    currentUrl = currentUrl.replace(/\/p-\d+/, `/p-${p}`);
                } else {
                    // Append new pagination parameter
                    if (currentUrl.includes('?')) {
                        // URL has query params but no page=, append it
                        currentUrl = `${currentUrl}&page=${p}`;
                    } else {
                        // URL has no query params, use the segment approach /p-N
                        const cleanUrl = currentUrl.replace(/\/$/, '');
                        currentUrl = `${cleanUrl}/p-${p}`;
                    }
                }

                appendLog(`--- PROCESSING PAGE ${p} of ${maxPages} ---`);
                appendLog(`URL: ${currentUrl}`);
                statusText.innerText = `Scraping P${p}...`;

                try {
                    const response = await fetch('/manual-scrape/process', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ url: currentUrl, maxScrolls })
                    });

                    const text = await response.text();
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        appendLog(`Page ${p} failed: Server error (Non-JSON).`, 'error');
                        continue; // Try next page
                    }

                    if (data.success) {
                        appendLog(`Page ${p} complete: Found ${data.count} items.`);
                        totalFound += data.count;
                        countBadge.innerText = `${totalFound} Total Found`;

                        // Add to UI results
                        data.results.forEach(item => {
                            const card = document.createElement('div');
                            card.className = 'bg-slate-800/60 p-4 rounded-xl border border-slate-700 hover:border-blue-500/50 transition-all cursor-pointer mb-4 animate-in fade-in slide-in-from-bottom-2 duration-300';
                            card.innerHTML = `
                                <div class="flex justify-between items-start gap-3">
                                    <div class="space-y-1 overflow-hidden">
                                        <div class="flex items-center gap-2">
                                            <span class="text-[9px] bg-blue-500/20 text-blue-400 px-1 rounded">PAGE ${p}</span>
                                            <h3 class="font-bold text-slate-100 truncate">${item.title}</h3>
                                        </div>
                                        <div class="space-y-0.5">
                                            <p class="text-[10px] text-blue-400 italic">${item.category || 'No Category'}</p>
                                            <p class="text-[9px] text-slate-500 line-clamp-1">${item.subcategories || ''}</p>
                                        </div>
                                        <p class="text-xs text-slate-400 mt-1">${item.location}</p>
                                        <div class="flex flex-wrap gap-2 mt-1">
                                            <span class="text-[10px] text-emerald-400 font-bold bg-emerald-400/10 px-1 rounded">${item.phone || 'No Phone'}</span>
                                            <span class="text-[10px] text-blue-400">ID: ${item.view_number}</span>
                                            <span class="text-[10px] text-slate-400 bg-slate-700 px-1 rounded">${item.locality || 'N/A'}</span>
                                            <span class="text-[10px] text-slate-100 bg-slate-600 px-1 rounded">${item.district || 'N/A'}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-3 flex gap-2 border-t border-slate-700/50 pt-3">
                                    <a href="${item.link}" target="_blank" class="text-[10px] uppercase font-bold text-slate-400 hover:text-white">Visit Page</a>
                                </div>
                            `;
                            resultsList.prepend(card);
                        });
                    } else {
                        appendLog(`Page ${p} error: ${data.error || 'Unknown'}`, 'error');
                    }
                } catch (err) {
                    appendLog(`Page ${p} connection error: ${err.message}`, 'error');
                }
                
                // Small pause between pages
                if (p < maxPages) {
                    appendLog('Waiting 2 seconds before next page...', 'info');
                    await new Promise(r => setTimeout(r, 2000));
                }
            }

            appendLog('--- BATCH PROCESS COMPLETED ---');
            statusText.innerText = 'All Done';
            statusDot.classList.replace('bg-blue-500', 'bg-emerald-500');
            statusDot.classList.remove('animate-pulse');
            startBtn.disabled = false;
        }

        window.viewHTML = (encodedHtml) => {
            const html = decodeURIComponent(encodedHtml);
            const win = window.open("", "_blank");
            win.document.body.innerText = html;
            setTimeout(() => {
                win.document.title = "Element HTML Source";
                win.document.body.style.fontFamily = "monospace";
                win.document.body.style.whiteSpace = "pre-wrap";
                win.document.body.style.background = "#0f172a";
                win.document.body.style.color = "#94a3b8";
            }, 50);
        };

        startBtn.addEventListener('click', runScrape);
    </script>
</body>
</html>
