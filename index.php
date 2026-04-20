<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>EMA App Download</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="public, max-age=3600">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --header-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --android-btn: linear-gradient(135deg, #4CAF50, #45a049);
            --shadow: 0 15px 30px rgba(0,0,0,0.1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        .header {
            background: var(--header-gradient);
            color: white;
            text-align: center;
            padding: 40px 20px;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header p { font-size: 1.1rem; opacity: 0.9; }
        .downloads { padding: 30px; }
        .platform {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            transition: transform 0.2s ease;
        }
        .platform:hover {
            transform: translateY(-5px);
        }
        .platform.recommended {
            border: 2px solid #4facfe;
            background: #f0f8ff;
        }
        .platform-icon { max-width: 80px; margin-bottom: 15px; }
        .platform h2 {
            font-size: 1.6rem;
            margin-bottom: 10px;
            color: #333;
        }
        .platform p {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .download-btn {
            display: inline-block;
            padding: 12px 25px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            border-radius: 50px;
            transition: transform 0.2s ease;
            cursor: pointer;
            min-width: 180px;
        }
        .btn-android {
            background: var(--android-btn);
            color: white;
        }
        .btn-disabled {
            background: #ccc;
            color: #666;
            cursor: not-allowed;
        }
        .download-btn:hover:not(.btn-disabled) {
            transform: translateY(-2px);
        }
        .app-info {
            background: #e8f5e8;
            border-left: 4px solid #4CAF50;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .app-info h3 {
            color: #2e7d32;
            margin-bottom: 8px;
        }
        .app-info p {
            color: #388e3c;
            line-height: 1.5;
        }
        .version-info {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            color: #666;
            font-size: 0.85rem;
        }
        .badge {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-left: 8px;
        }
        .error-message {
            display: none;
            background: #ffebee;
            border-left: 4px solid #d32f2f;
            padding: 10px;
            margin: 10px 0;
            color: #d32f2f;
            border-radius: 5px;
        }
        @media (max-width: 768px) {
            body { padding: 10px; }
            .header h1 { font-size: 2rem; }
            .downloads { padding: 15px; }
            .platform { padding: 15px; margin-bottom: 15px; }
            .platform h2 { font-size: 1.4rem; }
            .download-btn { width: 100%; margin: 8px 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://theemaeducation.com/folder_689d65065f916_ubt logo.jpg" alt="UBT Logo" style="max-width: 120px; margin-bottom: 15px;" crossorigin="anonymous">
            <h1>EMA App</h1>
            <p>Download now for a seamless experience</p>
        </div>
        <div class="downloads">
            <div class="platform recommended" id="android-platform">
                <img src="https://theemaeducation.com/folder_689d65065f916_ubt logo.jpg" alt="UBT Logo" class="platform-icon" crossorigin="anonymous">
                <h2>Android <span class="badge">Available</span></h2>
                <p>Direct APK download for Android devices.</p>
                <a href="https://theemaeducation.com/app-release.apk" download="ema_app.apk" class="download-btn btn-android">Download APK</a>
                <div class="error-message" id="error-android">Failed to track download. Please try again.</div>
            </div>
            <div class="app-info">
                <h3>About EMA App</h3>
                <p>Your go-to app for enhanced mobile access.</p>
            </div>
        </div>
        <div class="version-info">
            <p>Version 1.0 | Always available via CDN</p>
        </div>
    </div>
    <script>
        async function trackDownload(platform, retries = 2) {
            const errorElement = document.getElementById(`error-${platform.toLowerCase()}`);
            try {
                const controller = new AbortController();
                const timeoutId = setTimeout(() => controller.abort(), 10000); // 10s timeout
                const response = await fetch('https://theemaeducation.com/track_download.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ platform }),
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                if (!response.ok) throw new Error(`HTTP error: ${response.status}`);
                console.log(`Download tracked: ${platform}`);
                errorElement.style.display = 'none';
            } catch (err) {
                console.error('Analytics error:', err);
                if (retries > 0) {
                    console.log(`Retrying (${retries} left)...`);
                    return trackDownload(platform, retries - 1);
                }
                errorElement.style.display = 'block';
            }
        }

        document.querySelectorAll('.download-btn:not(.btn-disabled)').forEach(btn => 
            btn.addEventListener('click', () => {
                const platform = btn.textContent.includes('Android') ? 'Android' : 'Unknown';
                trackDownload(platform);
            })
        );

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('Service Worker registered:', reg))
                .catch(err => console.error('Service Worker failed:', err));
        }
    </script>
</body>
</html>