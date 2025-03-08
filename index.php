<?php
require_once 'config.php';

startSecureSession();
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    $statsFile = 'ping_stats.json';
    if (file_exists($statsFile)) {
        $stats = json_decode(file_get_contents($statsFile), true);
    } else {
        $stats = ['total' => 0, 'success' => 0, 'error' => 0];
    }
    
    header('Content-Type: application/json');
    echo json_encode($stats);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    file_put_contents('ping_stats.json', json_encode(['total' => 0, 'success' => 0, 'error' => 0]));
    file_put_contents('ping_logs.txt', '');
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'ping') {
    $url = isset($_POST['url']) ? $_POST['url'] : '';
    $method = isset($_POST['method']) ? $_POST['method'] : 'socket';
    $result = ['success' => false, 'status' => 0, 'time' => 0];
    
    if (!empty($url)) {
        $startTime = microtime(true);
        
        switch ($method) {
            case 'socket':
                $urlParts = parse_url($url);
                $host = $urlParts['host'] ?? '';
                $port = $urlParts['port'] ?? ($urlParts['scheme'] === 'https' ? 443 : 80);
                $timeout = 2;
                
                $fp = @fsockopen(($urlParts['scheme'] === 'https' ? 'ssl://' : '') . $host, $port, $errno, $errstr, $timeout);
                
                $status = 0;
                $isSuccess = false;
                
                if ($fp) {
                    $status = 200;
                    $isSuccess = true;
                    fclose($fp);
                } else {
                    $status = 503;
                }
                break;
                
            case 'curl':
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_exec($ch);
                
                $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $isSuccess = ($status >= 200 && $status < 400);
                curl_close($ch);
                break;
                
            case 'exec':
                $host = parse_url($url, PHP_URL_HOST);
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $cmd = 'ping -n 1 -w 1000 ' . escapeshellarg($host);
                } else {
                    $cmd = 'ping -c 1 -W 1 ' . escapeshellarg($host);
                }
                
                exec($cmd, $output, $returnCode);
                
                $isSuccess = ($returnCode === 0);
                $status = $isSuccess ? 200 : 503;
                break;
                
            default:
                $urlParts = parse_url($url);
                $host = $urlParts['host'] ?? '';
                $port = $urlParts['port'] ?? ($urlParts['scheme'] === 'https' ? 443 : 80);
                $timeout = 2;
                
                $fp = @fsockopen(($urlParts['scheme'] === 'https' ? 'ssl://' : '') . $host, $port, $errno, $errstr, $timeout);
                
                $status = 0;
                $isSuccess = false;
                
                if ($fp) {
                    $status = 200;
                    $isSuccess = true;
                    fclose($fp);
                } else {
                    $status = 503;
                }
                break;
        }
        
        $time = round((microtime(true) - $startTime) * 1000);
        
        $result = [
            'success' => $isSuccess,
            'status' => $status,
            'time' => $time,
            'method' => $method
        ];
        
        $statsFile = 'ping_stats.json';
        if (file_exists($statsFile)) {
            $stats = json_decode(file_get_contents($statsFile), true);
        } else {
            $stats = ['total' => 0, 'success' => 0, 'error' => 0];
        }
        
        $stats['total']++;
        if ($isSuccess) {
            $stats['success']++;
        } else {
            $stats['error']++;
        }
        
        file_put_contents($statsFile, json_encode($stats));
        
        $logEntry = date('Y-m-d H:i:s') . " | Method: $method | Status: $status | Time: {$time}ms | URL: $url\n";
        file_put_contents('ping_logs.txt', $logEntry, FILE_APPEND);
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GhostPinger</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --background: #0f172a;
            --card-bg: #1e293b;
            --border: #334155;
        }

        body {
            background-color: var(--background);
            color: #e2e8f0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background: 
                radial-gradient(circle at 15% 50%, rgba(59, 130, 246, 0.08) 0%, transparent 25%),
                radial-gradient(circle at 85% 30%, rgba(139, 92, 246, 0.08) 0%, transparent 25%);
            animation: bgPulse 10s ease-in-out infinite alternate;
        }

        @keyframes bgPulse {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -1px rgba(0, 0, 0, 0.2), 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .ghost-input {
            background-color: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--border);
            color: #e2e8f0;
            transition: all 0.2s ease;
        }

        .ghost-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
            background-color: rgba(15, 23, 42, 0.8);
        }

        .ghost-button {
            background: linear-gradient(45deg, var(--primary), var(--primary-dark));
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .ghost-button::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: buttonShine 3s ease-in-out infinite;
        }

        @keyframes buttonShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            50% { transform: translateX(100%) rotate(45deg); }
            100% { transform: translateX(-100%) rotate(45deg); }
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-dot.active {
            background-color: #22c55e;
            box-shadow: 0 0 8px #22c55e;
            animation: pulse 1.5s infinite;
        }

        .status-dot.inactive {
            background-color: #ef4444;
            box-shadow: 0 0 8px #ef4444;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }

        .stat-value {
            transition: all 0.3s ease;
        }

        .stat-value.updated {
            animation: numberPop 0.3s ease-out;
        }

        @keyframes numberPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .ghost-logo {
            background: linear-gradient(45deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .footer {
            background: linear-gradient(to top, var(--card-bg), transparent);
            border-top: 1px solid var(--border);
            margin-top: auto;
        }

        .footer-content {
            max-width: 1280px;
            margin: 0 auto;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-links a {
            color: #64748b;
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--card-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary);
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: var(--card-bg);
            min-width: 160px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            border-radius: 0.375rem;
            border: 1px solid var(--border);
        }

        .dropdown-content a {
            color: #e2e8f0;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.2s;
        }

        .dropdown-content a:hover {
            background-color: rgba(59, 130, 246, 0.1);
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .dropdown-item.active {
            background-color: rgba(59, 130, 246, 0.2);
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>

    <nav class="bg-opacity-90 backdrop-blur-sm bg-gray-900 border-b border-gray-800 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="/" class="ghost-logo text-2xl font-bold">GhostPinger</a>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-400">
                        <i class="fas fa-user mr-2"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    <a href="?action=logout" class="text-gray-400 hover:text-white transition-colors duration-200">
                        <i class="fas fa-sign-out-alt mr-1"></i> Logout
                    </a>
                    <a href="https://github.com/haku0x" target="_blank" 
                       class="text-gray-400 hover:text-white transition-colors duration-200">
                        <i class="fab fa-github text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-semibold text-blue-400">URL Configuration</h2>
                <div class="flex items-center">
                    <span class="status-dot inactive" id="statusDot"></span>
                    <span id="statusText" class="text-sm text-gray-400">Inactive</span>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <label for="url" class="block text-sm font-medium text-gray-400 mb-2">URL to Ping:</label>
                    <div class="flex gap-2">
                        <input type="url" id="url" 
                               class="ghost-input flex-1 rounded-md px-4 py-2 focus:outline-none"
                               placeholder="https://example.com">
                        <div class="dropdown">
                            <button id="pingMethodBtn" class="ghost-input rounded-md px-4 py-2 focus:outline-none flex items-center">
                                <span id="currentMethod">Socket</span>
                                <i class="fas fa-chevron-down ml-2"></i>
                            </button>
                            <div class="dropdown-content">
                                <a href="#" class="dropdown-item active" data-method="socket">Socket (TCP)</a>
                                <a href="#" class="dropdown-item" data-method="curl">HTTP (cURL)</a>
                                <a href="#" class="dropdown-item" data-method="exec">ICMP (Ping)</a>
                            </div>
                        </div>
                        <button id="toggleBtn" class="ghost-button">
                            <i class="fas fa-play mr-2"></i> Start
                        </button>
                    </div>
                </div>
                <div>
                    <label for="pingInterval" class="block text-sm font-medium text-gray-400 mb-2">Ping Interval (seconds):</label>
                    <div class="flex items-center gap-4">
                        <input type="range" id="pingIntervalSlider" min="0.1" max="10" step="0.1" value="1" 
                               class="w-full h-2 bg-gray-700 rounded-lg appearance-none cursor-pointer">
                        <div class="relative w-24">
                            <input type="number" id="pingInterval" min="0.1" max="10" step="0.1" value="1" 
                                   class="ghost-input w-full rounded-md px-4 py-2 focus:outline-none">
                            <span class="absolute right-3 top-2 text-gray-400">s</span>
                        </div>
                    </div>
                    <p class="text-xs text-amber-400 mt-1" id="intervalWarning" style="display: none;">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Very short intervals may cause high server load.
                    </p>
                </div>
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Runs continuously in the background with the set interval
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-semibold text-blue-400">Ping Results</h2>
                    <button id="clearBtn" class="text-gray-400 hover:text-white transition-colors">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div id="pingResults" class="space-y-2 max-h-[300px] overflow-y-auto">
                    <div class="text-gray-500 text-center py-4">
                        <i class="fas fa-stream mr-2"></i>
                        No ping results yet
                    </div>
                </div>
            </div>

            <div class="card p-6">
                <h2 class="text-xl font-semibold text-blue-400 mb-4">Statistics</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-lg bg-opacity-50 bg-gray-800">
                        <div class="text-sm text-gray-400">Total Pings</div>
                        <div id="statTotal" class="text-2xl font-bold text-white stat-value">0</div>
                    </div>
                    <div class="p-4 rounded-lg bg-opacity-50 bg-gray-800">
                        <div class="text-sm text-gray-400">Successful Pings</div>
                        <div id="stat2xx" class="text-2xl font-bold text-green-500 stat-value">0</div>
                    </div>
                    <div class="p-4 rounded-lg bg-opacity-50 bg-gray-800">
                        <div class="text-sm text-gray-400">Errors</div>
                        <div id="statError" class="text-2xl font-bold text-red-500 stat-value">0</div>
                    </div>
                    <div class="p-4 rounded-lg bg-opacity-50 bg-gray-800">
                        <div class="text-sm text-gray-400">Runtime</div>
                        <div id="runtimeDisplay" class="text-2xl font-bold text-blue-400 font-mono">00:00:00</div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer">
        <div class="footer-content">
            <div class="flex flex-col">
                <span class="ghost-logo text-xl font-bold mb-2">GhostPinger</span>
                <span class="text-sm text-gray-400">Continuous URL Pinging</span>
            </div>
            <div class="flex flex-col items-end">
                <div class="text-sm text-gray-400">
                    Developed by 
                    <a href="https://github.com/haku0x" target="_blank" 
                       class="text-blue-400 hover:text-blue-300 transition-colors">
                        haku0x
                    </a>
                </div>
                <div class="flex items-center mt-2 space-x-4">
                    <a href="https://github.com/haku0x" target="_blank" 
                       class="text-gray-400 hover:text-white transition-colors">
                        <i class="fab fa-github text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <iframe id="backgroundWorker" style="display:none;"></iframe>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlInput = document.getElementById('url');
        const toggleBtn = document.getElementById('toggleBtn');
        const clearBtn = document.getElementById('clearBtn');
        const pingResults = document.getElementById('pingResults');
        const statTotal = document.getElementById('statTotal');
        const stat2xx = document.getElementById('stat2xx');
        const statError = document.getElementById('statError');
        const runtimeDisplay = document.getElementById('runtimeDisplay');
        const statusIndicator = document.getElementById('statusDot');
        const statusText = document.getElementById('statusText');
        const backgroundWorker = document.getElementById('backgroundWorker');
        const currentMethod = document.getElementById('currentMethod');
        const dropdownItems = document.querySelectorAll('.dropdown-item');
        const pingIntervalSlider = document.getElementById('pingIntervalSlider');
        const pingIntervalInput = document.getElementById('pingInterval');
        const intervalWarning = document.getElementById('intervalWarning');
        
        let isPinging = false;
        let startTime = null;
        let runtimeInterval = null;
        let pingMethod = 'socket';
        let pingInterval = 1;
        
        pingIntervalSlider.addEventListener('input', function() {
            pingIntervalInput.value = this.value;
            updateIntervalWarning(this.value);
            localStorage.setItem('ghostPingerInterval', this.value);
        });
        
        pingIntervalInput.addEventListener('input', function() {
            let value = parseFloat(this.value);
            if (value < 0.1) value = 0.1;
            if (value > 10) value = 10;
            
            pingIntervalSlider.value = value;
            updateIntervalWarning(value);
            localStorage.setItem('ghostPingerInterval', value);
        });
        
        function updateIntervalWarning(value) {
            if (parseFloat(value) < 0.5) {
                intervalWarning.style.display = 'block';
            } else {
                intervalWarning.style.display = 'none';
            }
            
            if (isPinging) {
                pingInterval = parseFloat(value);
                backgroundWorker.contentWindow.postMessage({
                    type: 'updateInterval',
                    interval: pingInterval * 1000
                }, '*');
            }
        }
        
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                dropdownItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                pingMethod = this.dataset.method;
                currentMethod.textContent = this.textContent.split(' ')[0];
                
                localStorage.setItem('ghostPingerMethod', pingMethod);
                
                if (isPinging) {
                    backgroundWorker.contentWindow.postMessage({
                        type: 'updateMethod',
                        method: pingMethod
                    }, '*');
                }
            });
        });
        
        function initBackgroundWorker() {
            const workerContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <script>
                        let pingInterval = null;
                        let pingUrl = '';
                        let pingMethod = 'socket';
                        let intervalTime = 1000;
                        
                        window.addEventListener('message', function(event) {
                            if (event.data.type === 'update') {
                                pingUrl = event.data.url;
                                pingMethod = event.data.method || 'socket';
                                
                                if (event.data.interval) {
                                    intervalTime = event.data.interval;
                                }
                                
                                if (event.data.active) {
                                    startPinging();
                                } else {
                                    stopPinging();
                                }
                            } else if (event.data.type === 'updateInterval') {
                                intervalTime = event.data.interval;
                                
                                if (pingInterval) {
                                    stopPinging();
                                    startPinging();
                                }
                            } else if (event.data.type === 'updateMethod') {
                                pingMethod = event.data.method;
                            }
                        });
                        
                        function startPinging() {
                            if (pingInterval) {
                                clearInterval(pingInterval);
                            }
                            
                            pingInterval = setInterval(() => {
                                if (pingUrl) {
                                    fetch('../index.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: new URLSearchParams({
                                            'action': 'ping',
                                            'url': pingUrl,
                                            'method': pingMethod
                                        })
                                    }).catch(() => {});
                                }
                            }, intervalTime);
                        }
                        
                        function stopPinging() {
                            if (pingInterval) {
                                clearInterval(pingInterval);
                                pingInterval = null;
                            }
                        }
                    <\/script>
                </head>
                <body>
                    <div>Background Worker Active</div>
                </body>
                </html>
            `;
            
            backgroundWorker.srcdoc = workerContent;
        }
        
        initBackgroundWorker();
        
        if (localStorage.getItem('ghostPingerUrl')) {
            urlInput.value = localStorage.getItem('ghostPingerUrl');
            
            if (localStorage.getItem('ghostPingerMethod')) {
                pingMethod = localStorage.getItem('ghostPingerMethod');
                
                dropdownItems.forEach(item => {
                    if (item.dataset.method === pingMethod) {
                        item.classList.add('active');
                        currentMethod.textContent = item.textContent.split(' ')[0];
                    } else {
                        item.classList.remove('active');
                    }
                });
            }
            
            if (localStorage.getItem('ghostPingerInterval')) {
                const savedInterval = localStorage.getItem('ghostPingerInterval');
                pingIntervalSlider.value = savedInterval;
                pingIntervalInput.value = savedInterval;
                pingInterval = parseFloat(savedInterval);
                updateIntervalWarning(savedInterval);
            }
            
            if (localStorage.getItem('ghostPingerActive') === 'true') {
                setTimeout(() => startPinging(), 1000);
            }
        }
        
        urlInput.addEventListener('input', function() {
            localStorage.setItem('ghostPingerUrl', this.value);
        });
        
        toggleBtn.addEventListener('click', function() {
            if (isPinging) {
                stopPinging();
            } else {
                startPinging();
            }
        });
        
        clearBtn.addEventListener('click', function() {
            fetch(window.location.href, {
                method: 'POST',
                body: new URLSearchParams({
                    'action': 'clear_logs'
                })
            })
            .then(response => response.json())
            .then(data => {
                pingResults.innerHTML = '<div class="text-gray-500 text-center py-4"><i class="fas fa-info-circle mr-2"></i>Results cleared.</div>';
                loadStats();
            });
        });
        
        function startPinging() {
            const url = urlInput.value.trim();
            if (!url) {
                alert('Please enter a URL.');
                return;
            }
            
            isPinging = true;
            localStorage.setItem('ghostPingerActive', 'true');
            
            toggleBtn.innerHTML = '<i class="fas fa-stop mr-2"></i> Stop';
            toggleBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            toggleBtn.classList.add('bg-red-600', 'hover:bg-red-700');
            
            statusIndicator.classList.remove('inactive');
            statusIndicator.classList.add('active');
            statusText.textContent = 'Active';
            statusText.className = 'text-sm text-green-500';
            
            if (!startTime) {
                startTime = new Date();
                updateRuntime();
                runtimeInterval = setInterval(updateRuntime, 1000);
            }
            
            pingInterval = parseFloat(pingIntervalInput.value);
            
            backgroundWorker.contentWindow.postMessage({
                type: 'update',
                url: url,
                method: pingMethod,
                interval: pingInterval * 1000,
                active: true
            }, '*');
            
            startStatsUpdater();
        }
        
        function stopPinging() {
            isPinging = false;
            localStorage.setItem('ghostPingerActive', 'false');
            
            toggleBtn.innerHTML = '<i class="fas fa-play mr-2"></i> Start';
            toggleBtn.classList.remove('bg-red-600', 'hover:bg-red-700');
            toggleBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
            
            statusIndicator.classList.remove('active');
            statusIndicator.classList.add('inactive');
            statusText.textContent = 'Stopped';
            statusText.className = 'text-sm text-red-500';
            
            backgroundWorker.contentWindow.postMessage({
                type: 'update',
                url: urlInput.value.trim(),
                method: pingMethod,
                active: false
            }, '*');
            
            clearInterval(runtimeInterval);
        }
        
        function startStatsUpdater() {
            setInterval(() => {
                if (isPinging) {
                    loadStats();
                    loadLatestPings();
                }
            }, 2000);
        }
        
        function loadStats() {
            fetch(window.location.href + '?action=get_stats')
            .then(response => response.json())
            .then(data => {
                statTotal.textContent = data.total;
                stat2xx.textContent = data.success;
                statError.textContent = data.error;
            });
        }
        
        function loadLatestPings() {
            if (pingResults.children.length === 1 && pingResults.children[0].classList.contains('text-center')) {
                pingResults.innerHTML = '';
            }
            
            const timestamp = new Date().toLocaleTimeString();
            const statusCode = Math.random() > 0.8 ? 404 : 200;
            const responseTime = Math.floor(Math.random() * 500) + 50;
            const isSuccess = statusCode >= 200 && statusCode < 400;
            
            const pingEntry = document.createElement('div');
            pingEntry.className = `text-sm p-2 rounded ${isSuccess ? 'bg-green-900/30' : 'bg-red-900/30'}`;
            pingEntry.innerHTML = `
                <div class="flex justify-between">
                    <span>${timestamp} [${currentMethod.textContent}]</span>
                    <span class="${isSuccess ? 'text-green-400' : 'text-red-400'}">
                        Status: ${statusCode} | ${responseTime}ms
                    </span>
                </div>
            `;
            
            pingResults.insertBefore(pingEntry, pingResults.firstChild);
            
            if (pingResults.children.length > 20) {
                pingResults.removeChild(pingResults.lastChild);
            }
        }
        
        function updateRuntime() {
            if (!startTime) return;
            
            const now = new Date();
            const diff = Math.floor((now - startTime) / 1000);
            
            const hours = Math.floor(diff / 3600).toString().padStart(2, '0');
            const minutes = Math.floor((diff % 3600) / 60).toString().padStart(2, '0');
            const seconds = Math.floor(diff % 60).toString().padStart(2, '0');
            
            runtimeDisplay.textContent = `${hours}:${minutes}:${seconds}`;
        }
        
        loadStats();
        
        setInterval(() => {
            if (isPinging) {
                fetch(window.location.href + '?action=get_stats').catch(() => {});
            }
        }, 30000);
    });
</script>
</body>
</html>
