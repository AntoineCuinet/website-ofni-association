"use strict";

document.addEventListener("DOMContentLoaded", function() {

    // <svg xmlns="http://www.w3.org/2000/svg">
    //     <path class="line" />
    // </svg>

    /* Animation de la ligne */
    const svg = document.querySelector("svg");
    const line = document.querySelector(".line");

    // Configuration
    const config = {
      totalPoints: 25,         // Nombre de points
      viscosity: 10,           // Rigidité (plus faible = plus souple)
      damping: 0.18,            // Amortissement
      waveHeight: 65,          // Hauteur maximale de l'ondulation
      blockHeight: 150,        // Hauteur du bloc contenant la ligne
    };

    const points = [];
    let width = window.innerWidth;
    let height = svg.getBoundingClientRect().height;

    let lastMouseY = null; // Dernière position verticale de la souris

    // Initialisation des points
    function initPoints() {
      points.length = 0;
      const gap = width / (config.totalPoints - 1);

      for (let i = 0; i < config.totalPoints; i++) {
        points.push({
          x: i * gap,
          y: height / 2,
          baseY: height / 2,
          vx: 0,
        });
      }
    }

    // Mise à jour du chemin SVG
    function updatePath() {
      let d = `M ${points[0].x},${points[0].y}`;

      for (let i = 1; i < points.length; i++) {
        const prev = points[i - 1];
        const curr = points[i];
        const midX = (prev.x + curr.x) / 2;
        const midY = (prev.y + curr.y) / 2;

        d += ` Q ${prev.x},${prev.y} ${midX},${midY}`;
      }

      const last = points[points.length - 1];
      d += ` T ${last.x},${last.y}`;
      line.setAttribute("d", d);
    }

    // Animation de la ligne
    function animate() {
      points.forEach((point) => {
        const dy = point.baseY - point.y;
        point.vx += dy / config.viscosity;
        point.vx *= 1 - config.damping;
        point.y += point.vx;
      });

      updatePath();
      requestAnimationFrame(animate);
    }

    // Interaction avec la souris
    function onMouseMove(e) {
      const mouseX = e.clientX;
      const mouseY = e.clientY;
      const blockTop = svg.getBoundingClientRect().top;
      const blockBottom = blockTop + config.blockHeight;

      if (lastMouseY !== null) {
        const directionY = mouseY - lastMouseY; // Direction verticale de la souris

        // Vérifier si la souris traverse la ligne (par le haut ou le bas)
        if (
          (lastMouseY < blockTop + config.blockHeight / 2 && mouseY >= blockTop + config.blockHeight / 2) ||
          (lastMouseY > blockTop + config.blockHeight / 2 && mouseY <= blockTop + config.blockHeight / 2)
        ) {
          points.forEach((point) => {
            const dx = Math.abs(mouseX - point.x);
            const influenceRadius = config.waveHeight * 2;

            if (dx < influenceRadius) {
              const distanceFactor = 1 - dx / influenceRadius; // Plus proche, plus fort

              if (directionY > 0) {
                // La souris traverse la ligne vers le bas
                point.y = point.baseY + config.waveHeight * distanceFactor;
              } else if (directionY < 0) {
                // La souris traverse la ligne vers le haut
                point.y = point.baseY - config.waveHeight * distanceFactor;
              }
            }
          });
        }
      }

      lastMouseY = mouseY; // Mettre à jour la dernière position Y de la souris
    }

    // Redimensionnement
    function onResize() {
      width = window.innerWidth;
      height = svg.getBoundingClientRect().height;
      initPoints();
      updatePath();
    }

    // Écouteurs d'événements
    window.addEventListener("resize", onResize);
    window.addEventListener("mousemove", onMouseMove);

    // Initialisation
    initPoints();
    updatePath();
    animate();

});