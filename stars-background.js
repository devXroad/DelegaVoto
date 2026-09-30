document.addEventListener("DOMContentLoaded", () => {
    const canvas = document.getElementById("stars-canvas");
    if (!canvas) return;
    const ctx = canvas.getContext("2d");

    const dpr = window.devicePixelRatio || 1;
    let width = window.innerWidth;
    let height = window.innerHeight;
    
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);

    let stars = [];

    // Mouse interaction for subtle 3D rotation parallax
    let mouseX = width / 2;
    let mouseY = height / 2;
    let targetRotationX = 0;
    let targetRotationY = 0;
    let rotationX = 0;
    let rotationY = 0;

    window.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;
        // Map mouse position to a target rotation (slight tilt)
        targetRotationY = ((mouseX / width) - 0.5) * 1.5;
        targetRotationX = ((mouseY / height) - 0.5) * 1.5;
    });

    let focalLength = 1000;
    let sphereRadius = 0;

    function initStars() {
        stars = [];
        const numStars = 800;
        sphereRadius = Math.min(width, height) * 0.6; 

        for (let i = 0; i < numStars; i++) {
            // Base sphere coordinates
            const r = sphereRadius * (0.5 + Math.random() * 0.5); 
            const theta = Math.random() * 2 * Math.PI;
            const phi = Math.acos(2 * Math.random() - 1);

            const baseX = r * Math.sin(phi) * Math.cos(theta);
            const baseY = r * Math.sin(phi) * Math.sin(theta);
            const baseZ = r * Math.cos(phi);
            
            // Scatter coordinates (wide explosion bounds)
            const scatterX = (Math.random() - 0.5) * width * 3;
            const scatterY = (Math.random() - 0.5) * height * 3;
            const scatterZ = (Math.random() - 0.5) * focalLength * 3;

            stars.push({
                baseX: baseX,
                baseY: baseY,
                baseZ: baseZ,
                scatterX: scatterX,
                scatterY: scatterY,
                scatterZ: scatterZ,
                baseRadius: Math.random() * 1.8 + 0.5,
                alpha: Math.random(),
                alphaChange: (Math.random() * 0.02) + 0.005
            });
        }
    }

    let time = 0;

    function drawStars() {
        ctx.clearRect(0, 0, width, height);
        
        // Smoothly interpolate towards mouse rotation
        rotationX += (targetRotationX - rotationX) * 0.05;
        rotationY += (targetRotationY - rotationY) * 0.05;

        time += 0.002;

        const totalRotX = rotationX + time * 0.5;
        const totalRotY = rotationY + time;

        const cosX = Math.cos(totalRotX);
        const sinX = Math.sin(totalRotX);
        const cosY = Math.cos(totalRotY);
        const sinY = Math.sin(totalRotY);

        const centerX = width / 2;
        const centerY = height / 2;
        
        // Precise timing for the scatter effect
        const timeInSeconds = performance.now() / 1000;
        const animationDuration = 6.0; // Slower, more majestic explosion/return
        const waitDuration = 4.0;      // Time to wait between explosions
        const cycleDuration = animationDuration + waitDuration;
        
        let currentCycle = timeInSeconds % cycleDuration;
        let scatterFactor = 0;
        
        if (currentCycle < animationDuration) {
            // Animate from 0 to 1 and back to 0 using a sine wave
            let normalized = currentCycle / animationDuration; 
            scatterFactor = Math.sin(normalized * Math.PI);
            // Quadratic easing for smoother start/end
            scatterFactor = scatterFactor * scatterFactor;
        }

        const projectedStars = [];

        stars.forEach(star => {
            // Twinkling effect
            star.alpha += star.alphaChange;
            if (star.alpha <= 0.1 || star.alpha >= 0.8) {
                star.alphaChange = -star.alphaChange;
            }

            // Interpolate position between base sphere and scatter
            const currentX = star.baseX + (star.scatterX - star.baseX) * scatterFactor;
            const currentY = star.baseY + (star.scatterY - star.baseY) * scatterFactor;
            const currentZ = star.baseZ + (star.scatterZ - star.baseZ) * scatterFactor;

            // Apply 3D Rotation (around Y, then around X)
            let tempX = currentX * cosY - currentZ * sinY;
            let tempZ = currentZ * cosY + currentX * sinY;
            
            let tempY = currentY * cosX - tempZ * sinX;
            let finalZ = tempZ * cosX + currentY * sinX;

            // Push object into the distance away from camera
            let zPos = finalZ + focalLength; 

            if (zPos > 0) {
                // Perspective projection scale
                const scale = focalLength / zPos;
                const xProj = centerX + tempX * scale;
                const yProj = centerY + tempY * scale;

                projectedStars.push({
                    x: xProj,
                    y: yProj,
                    z: finalZ,
                    r: Math.max(0.1, star.baseRadius * scale),
                    alpha: star.alpha
                });
            }
        });

        // Depth sorting (painter's algorithm) - draw furthest stars first
        projectedStars.sort((a, b) => b.z - a.z);

        projectedStars.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
            
            // Fading stars that are further back (fog effect)
            const depthFactor = 1 - ((p.z + focalLength * 0.5) / focalLength);
            const clampedDepth = Math.max(0.1, Math.min(1, depthFactor));
            const finalAlpha = Math.min(1, p.alpha * clampedDepth);

            ctx.fillStyle = `rgba(0, 0, 0, ${finalAlpha})`;
            ctx.fill();
        });

        requestAnimationFrame(drawStars);
    }

    initStars();
    drawStars();

    window.addEventListener("resize", () => {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width * dpr;
        canvas.height = height * dpr;
        ctx.scale(dpr, dpr);
        initStars();
    });
});
