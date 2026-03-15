<?php
session_start();

// Security monitoring
require_once __DIR__ . '/config/security_monitor.php';

// Récupérer les infos IP via une API gratuite
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$details = @json_decode(@file_get_contents("http://ip-api.com/json/$ip"));

$ville = $details->city ?? "Inconnue";
$fai = $details->isp ?? "Inconnu";
$pays = $details->country ?? "Inconnu";
$lat = $details->lat ?? 46.2276;
$lng = $details->lon ?? 2.2137;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ACCÈS RESTREINT - Terminal Sécurisé</title>
    <link rel="icon" type="image/x-icon" href="cache.ico">
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin="anonymous"
/>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', Consolas, 'Lucida Console', monospace;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Matrix Rain Effect */
        #matrix-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            opacity: 0.1;
        }

        /* Main Container */
        .terminal-container {
            position: relative;
            z-index: 10;
            max-width: 950px;
            margin: 30px auto;
            padding: 15px;
        }

        /* Terminal Window */
        .terminal {
            background: rgba(0, 15, 0, 0.95);
            border: 2px solid #00ff00;
            border-radius: 10px;
            box-shadow: 
                0 0 30px rgba(0, 255, 0, 0.4),
                inset 0 0 80px rgba(0, 255, 0, 0.03);
            overflow: hidden;
        }

        /* Terminal Header */
        .terminal-header {
            background: linear-gradient(180deg, #002200, #000d00);
            padding: 10px 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid #00ff00;
        }

        .terminal-btn {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .terminal-btn.red { background: #ff5f56; }
        .terminal-btn.yellow { background: #ffbd2e; }
        .terminal-btn.green { background: #27ca40; }

        .terminal-title {
            margin-left: 15px;
            font-size: 12px;
            color: #00ff00;
            opacity: 0.7;
        }

        /* Terminal Body */
        .terminal-body {
            padding: 20px;
            min-height: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }

        /* Typing Effect Cursor */
        .cursor {
            display: inline-block;
            width: 10px;
            height: 18px;
            background: #00ff00;
            animation: blink 1s infinite;
            vertical-align: middle;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        /* Text Styles */
        .welcome-text {
            font-size: 20px;
            margin-bottom: 15px;
            text-shadow: 0 0 10px #00ff00;
        }

        .system-message {
            color: #00ff00;
            margin: 5px 0;
        }

        .warning {
            color: #ffaa00;
            text-shadow: 0 0 5px #ffaa00;
        }

        .error {
            color: #ff3333;
            text-shadow: 0 0 5px #ff3333;
        }

        .info {
            color: #00ffff;
            text-shadow: 0 0 5px #00ffff;
        }

        .success {
            color: #00ff00;
        }

        /* ASCII Art */
        .ascii-art {
            font-size: 8px;
            line-height: 1.1;
            margin: 10px 0;
            white-space: pre;
            color: #00ff00;
            text-shadow: 0 0 5px #00ff00;
            display: none;
        }

        .ascii-art.visible {
            display: block;
        }

        /* Progress Bar */
        .progress-container {
            margin: 10px 0;
            display: none;
        }

        .progress-container.visible {
            display: block;
        }

        .progress-bar {
            display: inline-block;
            background: #001a00;
            border: 1px solid #00ff00;
            padding: 2px;
            width: 250px;
        }

        .progress-fill {
            display: inline-block;
            height: 14px;
            background: linear-gradient(90deg, #00ff00, #00aa00);
            transition: width 0.3s;
        }

        .progress-text {
            margin-left: 10px;
            font-size: 12px;
        }

        /* Map Container */
        #map-container {
            display: none;
            margin: 15px 0;
            border: 2px solid #00ff00;
            border-radius: 5px;
            overflow: hidden;
        }

        #map-container.visible {
            display: block;
        }

        #map {
            height: 200px;
            width: 100%;
            background: #001100;
        }

        /* File System */
        .file-system {
            margin: 15px 0;
            padding: 10px;
            border: 1px dashed #00ff00;
            background: rgba(0, 50, 0, 0.3);
            display: none;
        }

        .file-system.visible {
            display: block;
        }

        .file-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .file-item {
            padding: 8px 15px;
            background: rgba(0, 255, 0, 0.1);
            border: 1px solid #00aa00;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 12px;
            text-decoration: none;
            color: #00ff00;
            display: inline-block;
        }

        .file-item:hover {
            background: rgba(0, 255, 0, 0.2);
            border-color: #00ff00;
            box-shadow: 0 0 10px rgba(0, 255, 0, 0.3);
        }

        .file-item::before {
            content: "📄 ";
        }

        /* Access Denied Modal */
        .access-denied {
            display: none;
            margin: 10px 0;
            padding: 15px;
            background: rgba(255, 0, 0, 0.2);
            border: 2px solid #ff0000;
            border-radius: 5px;
            animation: shake 0.5s;
        }

        .access-denied.visible {
            display: block;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .access-denied h4 {
            color: #ff0000;
            margin-bottom: 5px;
        }

        /* Scan Lines Effect */
        .scanlines {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                0deg,
                rgba(0, 0, 0, 0.1),
                rgba(0, 0, 0, 0.1) 1px,
                transparent 1px,
                transparent 2px
            );
            pointer-events: none;
            z-index: 100;
        }

        /* Glitch Effect */
        .glitch {
            animation: glitch 2s infinite;
        }

        @keyframes glitch {
            0% { text-shadow: 2px 0 #ff0000, -2px 0 #00ffff; }
            25% { text-shadow: -2px 0 #ff0000, 2px 0 #00ffff; }
            50% { text-shadow: 2px 0 #00ff00, -2px 0 #ff00ff; }
            75% { text-shadow: -2px 0 #00ff00, 2px 0 #ffff00; }
            100% { text-shadow: 2px 0 #ff0000, -2px 0 #00ffff; }
        }

        /* System Info */
        .system-info {
            font-size: 10px;
            opacity: 0.5;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #003300;
            display: none;
        }

        .system-info.visible {
            display: block;
        }

        /* CRT Screen Flicker */
        @keyframes flicker {
            0% { opacity: 0.97; }
            5% { opacity: 0.94; }
            10% { opacity: 0.9; }
            15% { opacity: 0.95; }
            20% { opacity: 0.99; }
            50% { opacity: 0.94; }
            80% { opacity: 0.88; }
            100% { opacity: 0.97; }
        }

        body {
            animation: flicker 0.15s infinite;
        }

        /* Scrollbar */
        .terminal-body::-webkit-scrollbar {
            width: 8px;
        }

        .terminal-body::-webkit-scrollbar-track {
            background: #001100;
        }

        .terminal-body::-webkit-scrollbar-thumb {
            background: #00aa00;
            border-radius: 4px;
        }

        /* Loading dots animation */
        .loading-dots::after {
            content: '';
            animation: dots 1.5s infinite;
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }

        /* Navigation Links */
        .nav-links {
            margin-top: 20px;
            border-top: 1px dashed #00ff00;
            padding-top: 15px;
            display: none;
        }

        .nav-links.visible {
            display: block;
        }

        .nav-links a {
            color: #00ff00;
            text-decoration: none;
            display: block;
            padding: 8px 0;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            padding-left: 15px;
            background: rgba(0, 255, 0, 0.1);
            text-shadow: 0 0 10px #00ff00;
        }

        .nav-links a::before {
            content: "> ";
            opacity: 0;
            transition: opacity 0.3s;
        }

        .nav-links a:hover::before {
            opacity: 1;
        }

        /* Final cursor */
        .final-cursor {
            display: none;
            margin-top: 20px;
        }

        .final-cursor.visible {
            display: block;
        }
    </style>
</head>
<body>
    <canvas id="matrix-canvas"></canvas>
    <div class="scanlines"></div>

    <div class="terminal-container">
        <div class="terminal">
            <div class="terminal-header">
                <div class="terminal-btn red"></div>
                <div class="terminal-btn yellow"></div>
                <div class="terminal-btn green"></div>
                <span class="terminal-title">secure_terminal_v2.4.1 - restricted_access</span>
            </div>
            <div class="terminal-body" id="terminal-body">
                
                <!-- ASCII Art -->
                <pre class="ascii-art" id="ascii-art">
   _______  _______  _______  _______ 
  |       ||       ||       ||       |
  |   _   ||   _   ||   _   ||   _   |
  |  | |  ||  | |  ||  | |  ||  | |  |
  |  |_|  ||  |_|  ||  |_|  ||  |_|  |
  |       ||       ||       ||       |
  |_______||_______||_______||_______|
  
  ████████╗ █████╗ ████████╗██╗   ██╗
  ╚══██╔══╝██╔══██╗╚══██╔══╝╚██╗ ██╔╝
     ██║   ███████║   ██║    ╚████╔╝ 
     ██║   ██╔══██║   ██║     ╚██╔╝  
     ██║   ██║  ██║   ██║      ██║   
     ╚═╝   ╚═╝  ╚═╝   ╚═╝      ╚═╝   
                </pre>

                <!-- Welcome Text -->
                <div class="welcome-text glitch" id="welcome-text" style="display:none;">⚠ ACCÈS AUTORISÉ ⚠</div>

                <!-- Map Container -->
                <div id="map-container">
                    <div id="map"></div>
                </div>

                <!-- Progress -->
                <div class="progress-container" id="progress-container">
                    <div class="progress-bar">
                        <span class="progress-fill" id="progress-fill"></span>
                    </div>
                    <span class="progress-text" id="progress-text">0%</span>
                </div>

                <!-- File System -->
                <div class="file-system" id="file-system">
                    <div style="margin-bottom: 5px;">📁 /documents_classifies/</div>
                    <div class="file-list">
                        <a href="guide_operation.php" class="file-item">guide_operations.txt</a>
                        <a href="protocoles_secrets.php" class="file-item">protocoles_secrets.pdf</a>
                        <a href="donnees_sensibles.php" class="file-item">donnees_sensibles.dat</a>
                        <a href="contacts_confidentiels.php" class="file-item">contacts_confidentiels.db</a>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="nav-links" id="nav-links">
                    <p class="warning">>>> NAVIGATION:</p>
                    <a href="index.php">← Retour au site public</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="connexion.php">Connexion</a>
                    <a href="createcompte.php">Creation de compte</a>
                    <?php else: ?>
                    <a href="compte.php">Parametres du compte</a>
                    <a href="deconnexion.php">Deconnexion</a>
                    <?php endif; ?>
                </div>

                <!-- System Info -->
                <div class="system-info" id="system-info">
                    <p>Système: Debian GNU/Linux 12 (secure) | Uptime: 99.9% | Sessions: 1</p>
                    <p>Dernière connexion: <?php echo date('Y-m-d H:i:s'); ?></p>
                </div>

                <!-- Final Cursor -->
                <div class="final-cursor" id="final-cursor">
                    <span class="prompt">root@system:~#</span><span class="cursor"></span>
                </div>
            </div>
        </div>
    </div>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin="anonymous">
    </script>
    
    <script>
        // Matrix Rain Effect
        const canvas = document.getElementById('matrix-canvas');
        const ctx = canvas.getContext('2d');

        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@#$%^&*()_+-=[]{}|;:,.<>?';
        const charArray = chars.split('');
        const fontSize = 14;
        const columns = canvas.width / fontSize;
        const drops = [];

        for (let i = 0; i < columns; i++) {
            drops[i] = 1;
        }

        function drawMatrix() {
            ctx.fillStyle = 'rgba(0, 0, 0, 0.05)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            ctx.fillStyle = '#00ff00';
            ctx.font = fontSize + 'px monospace';

            for (let i = 0; i < drops.length; i++) {
                const char = charArray[Math.floor(Math.random() * charArray.length)];
                ctx.fillText(char, i * fontSize, drops[i] * fontSize);

                if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
                    drops[i] = 0;
                }
                drops[i]++;
            }
        }

        setInterval(drawMatrix, 50);

        window.addEventListener('resize', () => {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        });

        // Terminal Body
        const terminalBody = document.getElementById('terminal-body');

        // Variables PHP injectées
        const userIP = "<?php echo htmlspecialchars($ip); ?>";
        const userVille = "<?php echo htmlspecialchars($ville); ?>";
        const userPays = "<?php echo htmlspecialchars($pays); ?>";
        const userFAI = "<?php echo htmlspecialchars($fai); ?>";
        const userLat = <?php echo floatval($lat); ?>;
        const userLng = <?php echo floatval($lng); ?>;

        // Typing function for terminal messages
        function typeMessage(text, speed = 30) {
            return new Promise(resolve => {
                const line = document.createElement('div');
                line.className = 'system-message';
                terminalBody.appendChild(line);
                
                let i = 0;
                function typing() {
                    if (i < text.length) {
                        line.innerHTML += text.charAt(i);
                        i++;
                        terminalBody.scrollTop = terminalBody.scrollHeight;
                        setTimeout(typing, speed);
                    } else {
                        resolve();
                    }
                }
                typing();
            });
        }

        // Progress Bar
        function runProgressBar() {
            return new Promise(resolve => {
                document.getElementById('progress-container').classList.add('visible');
                
                const progressFill = document.getElementById('progress-fill');
                const progressText = document.getElementById('progress-text');
                
                let percent = 0;
                const interval = setInterval(() => {
                    percent += Math.random() * 15;
                    if (percent >= 100) {
                        percent = 100;
                        clearInterval(interval);
                        progressText.textContent = '100% - Terminé';
                        progressFill.style.width = '100%';
                        setTimeout(resolve, 500);
                    } else {
                        progressText.textContent = Math.floor(percent) + '%';
                        progressFill.style.width = percent + '%';
                    }
                }, 100);
            });
        }

        // Map instance
        let map = null;

        // Initialize map with real user location
        function initMap() {
            // Use real coordinates from PHP
            const userLatVal = typeof userLat !== 'undefined' ? userLat : 46.2276;
            const userLngVal = typeof userLng !== 'undefined' ? userLng : 2.2137;
            
            map = L.map('map', {
                zoomControl: false,
                attributionControl: false
            }).setView([userLatVal, userLngVal], 10);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 15,
                minZoom: 5
            }).addTo(map);
            
            // Circle at user's actual location
            L.circle([userLatVal, userLngVal], {
                color: '#00ff00',
                fillColor: '#00ff00',
                fillOpacity: 0.3,
                radius: 10000
            }).addTo(map);
        }

        // Access Denied for files
        function showAccessDenied(element) {
            const denied = document.getElementById('access-denied');
            denied.classList.add('visible');
            denied.scrollIntoView({ behavior: 'smooth' });
        }

        // Main animation sequence
        async function startAnimation() {
            // Show ASCII Art
            await new Promise(r => setTimeout(r, 300));
            document.getElementById('ascii-art').classList.add('visible');
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 500));

            // Show Welcome Text
            document.getElementById('welcome-text').style.display = 'block';
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 500));

            // Start typing messages
            await typeMessage("> Initialisation du système de sécurité...", 30);
            await typeMessage("> Vérification des credentials...", 25);
            await typeMessage("> Niveau d'accès: RESTREINT", 20);
            await typeMessage("> Cryptage: ACTIF", 20);
            await typeMessage("> Connexion sécurisée établie", 20);
            await typeMessage("> IP détectée: " + userIP, 15);
            await typeMessage("> Localisation: " + userVille + ", " + userPays, 15);
            await typeMessage("> FAI: " + userFAI, 15);
            
            // Show map
            document.getElementById('map-container').classList.add('visible');
            initMap();
            setTimeout(() => {
                if (map) map.invalidateSize();
            }, 100);
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 500));
            
            // Show file system
            document.getElementById('file-system').classList.add('visible');
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 300));
            
            // Show navigation
            document.getElementById('nav-links').classList.add('visible');
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 200));
            
            // Show system info
            document.getElementById('system-info').classList.add('visible');
            terminalBody.scrollTop = terminalBody.scrollHeight;
            await new Promise(r => setTimeout(r, 200));
            
            // Show final cursor
            document.getElementById('final-cursor').classList.add('visible');
            terminalBody.scrollTop = terminalBody.scrollHeight;
        }

        // Start the animation
        startAnimation();
    </script>
</body>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-PFBBVT0TVK"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-PFBBVT0TVK');
</script>

</html>

