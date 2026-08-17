/**
 * Animated low-poly network background — teal nodes connected by lines,
 * drifting slowly, matching the login page's neon-network look.
 * Used on login.php, register.php and index.php so the effect/colors
 * stay identical across pages.
 */
(function () {
    function initNetworkBg(canvasId) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        let width, height, points;

        const DOT_COLOR = 'rgba(56, 224, 209, 0.9)';
        const LINE_COLOR = 'rgba(56, 224, 209, OPACITY)';
        const POINT_COUNT_DIVISOR = 9000; // lower = more points
        const MAX_LINK_DIST = 150;

        function resize() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
            const count = Math.min(110, Math.max(35, Math.floor((width * height) / POINT_COUNT_DIVISOR)));
            points = Array.from({ length: count }, () => ({
                x: Math.random() * width,
                y: Math.random() * height,
                vx: (Math.random() - 0.5) * 0.35,
                vy: (Math.random() - 0.5) * 0.35,
                r: Math.random() * 1.6 + 1,
            }));
        }

        function tick() {
            ctx.clearRect(0, 0, width, height);

            for (const p of points) {
                p.x += p.vx;
                p.y += p.vy;
                if (p.x < 0 || p.x > width) p.vx *= -1;
                if (p.y < 0 || p.y > height) p.vy *= -1;
            }

            for (let i = 0; i < points.length; i++) {
                for (let j = i + 1; j < points.length; j++) {
                    const a = points[i], b = points[j];
                    const dx = a.x - b.x, dy = a.y - b.y;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    if (dist < MAX_LINK_DIST) {
                        const opacity = (1 - dist / MAX_LINK_DIST) * 0.5;
                        ctx.strokeStyle = LINE_COLOR.replace('OPACITY', opacity.toFixed(3));
                        ctx.lineWidth = 1;
                        ctx.beginPath();
                        ctx.moveTo(a.x, a.y);
                        ctx.lineTo(b.x, b.y);
                        ctx.stroke();
                    }
                }
            }

            ctx.fillStyle = DOT_COLOR;
            for (const p of points) {
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                ctx.fill();
                ctx.shadowColor = DOT_COLOR;
                ctx.shadowBlur = 6;
            }

            requestAnimationFrame(tick);
        }

        window.addEventListener('resize', resize);
        resize();
        tick();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initNetworkBg('network-bg-canvas');
    });
})();
