<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Status</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .container {
            text-align: center;
            color: white;
            position: relative;
            z-index: 2;
        }

        .status-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transform: translateY(0);
            animation: float 6s ease-in-out infinite;
            min-width: 400px;
        }

        .pulse-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            position: relative;
        }

        .pulse-icon::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 60px;
            height: 60px;
            background: #4ade80;
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: pulse 2s infinite;
        }

        .pulse-icon::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 30px;
            color: white;
            font-weight: bold;
            z-index: 1;
        }

        .title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 15px;
            background: linear-gradient(45deg, #ffffff, #e0e7ff);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s ease-in-out infinite;
        }

        .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            margin-bottom: 30px;
        }

        .status-info {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .info-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 20px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            flex: 1;
            min-width: 120px;
        }

        .info-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: bold;
        }

        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }

        .floating-shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: floatShapes 20s infinite linear;
        }

        .shape-1 {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape-2 {
            width: 120px;
            height: 120px;
            top: 70%;
            right: 10%;
            animation-delay: -5s;
        }

        .shape-3 {
            width: 60px;
            height: 60px;
            top: 40%;
            left: 5%;
            animation-delay: -10s;
        }

        .shape-4 {
            width: 100px;
            height: 100px;
            bottom: 20%;
            right: 20%;
            animation-delay: -15s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes pulse {
            0% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }

            50% {
                transform: translate(-50%, -50%) scale(1.2);
                opacity: 0.7;
            }

            100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 1;
            }
        }

        @keyframes shimmer {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        @keyframes floatShapes {
            0% {
                transform: translateY(0px) rotate(0deg);
            }

            33% {
                transform: translateY(-30px) rotate(120deg);
            }

            66% {
                transform: translateY(10px) rotate(240deg);
            }

            100% {
                transform: translateY(0px) rotate(360deg);
            }
        }

        .version-tag {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            backdrop-filter: blur(10px);
        }

        @media (max-width: 768px) {
            .status-card {
                margin: 20px;
                padding: 30px 25px;
                min-width: unset;
            }

            .title {
                font-size: 2rem;
            }

            .status-info {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>

<body>
    <div class="version-tag">v1.0</div>

    <div class="background-animation">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
        <div class="floating-shape shape-4"></div>
    </div>

    <div class="container">
        <div class="status-card">
            <div class="pulse-icon"></div>
            <h1 class="title">API is Alive!</h1>
            <p class="subtitle">Backend services are running smoothly</p>

            <div class="status-info">
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value">Online</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Response</div>
                    <div class="info-value">200 OK</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Server</div>
                    <div class="info-value">Active</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function () {
            const card = document.querySelector('.status-card');

            // Add subtle mouse tracking effect
            document.addEventListener('mousemove', function (e) {
                const x = (e.clientX / window.innerWidth) * 10;
                const y = (e.clientY / window.innerHeight) * 10;

                card.style.transform = `translateY(0) rotateX(${y - 5}deg) rotateY(${x - 5}deg)`;
            });

            // Reset on mouse leave
            document.addEventListener('mouseleave', function () {
                card.style.transform = 'translateY(0) rotateX(0) rotateY(0)';
            });

            // Update timestamp periodically
            setInterval(function () {
                const now = new Date();
                const timeString = now.toLocaleTimeString();
                console.log('API Status checked at:', timeString);
            }, 30000);
        });
    </script>
</body>

</html>