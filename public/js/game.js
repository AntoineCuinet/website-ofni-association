"use strict";

document.addEventListener("DOMContentLoaded", function () {
    /*********************************************************************************/
    /*                                    CONST                                      */
    /*********************************************************************************/
    const gameContainer = document.querySelector(".game-container");
    const gameTeamChoice = document.querySelector(".game-team-container");
    const gameInfoContainer = document.querySelector(".game-info-container");
    const btnBeeStart = document.getElementById("btn-bee-start-game");
    const btnDuckStart = document.getElementById("btn-duck-start-game");
    const btnStart = document.getElementById("btn-start-game");
    const scoreElement = document.getElementById("score-text");
    const levelElement = document.getElementById("level-text");
    const livesElement = document.getElementById("lives-text");
    const scoreFinal = document.getElementById("score");
    const teamChoice = document.getElementById("team-id");
    const quit = document.getElementById("quit-button");
    const musicButton = document.getElementById('music-button');

    let team = 0;
    let isPaused = true;
    let audio1 = new Audio();
    audio1.src = '../js/sounds/background-music.mp3';

    let isSoundEnabled = localStorage.getItem('Music');

    if (isSoundEnabled === null) {
        isSoundEnabled = true;
        localStorage.setItem('Music', isSoundEnabled);
    } else {
        isSoundEnabled = isSoundEnabled === 'true';
    }
    

    /*********************************************************************************/
    /*                                   LISTENER                                    */
    /*********************************************************************************/
    if (musicButton) {
        musicButton.addEventListener('click', toggleMusic);
    }

    if (btnBeeStart) {
        btnBeeStart.addEventListener("click", () => startGame(1));
        team = 1;
    }

    if (btnDuckStart) {
        btnDuckStart.addEventListener("click", () => startGame(2));
        team = 2;
    }

    if (btnStart) {
        btnStart.addEventListener("click", () => startGame(team));
    }

    if (quit) {
        quit.addEventListener("click", () => {
            if (confirm(`❗️ Es-tu sûr de vouloir quitter ? Ton score ne sera pas comptabiliser ❗️`)) {
                window.location.reload();
            }
        });
    }


    /*********************************************************************************/
    /*                                  CLASS GAME                                   */
    /*********************************************************************************/
    class Game {
        constructor(canvasId) {
            this.canvas = document.getElementById(canvasId);
            if (!this.canvas) throw new Error("Canvas element not found");
            this.ctx = this.canvas.getContext("2d", { antialias: false });
            this.player = new Player(this.canvas.width / 2 - 20, this.canvas.height - 10);
            this.aliens = [];
            this.bullets = [];
            this.isRunning = true;
            this.score = 0;
            this.level = 1;
            this.currentLevelConfig = null; // Configuration du niveau actuel
            this.loadLevel(this.level); // Charger le premier niveau
            this.setupControls();
            this.lastTime = 0;
        }

        loadLevel(level) {
            // this.currentLevelConfig = levels.find(l => l.level === level);
            // if (!this.currentLevelConfig) {
            //     console.error("Niveau non trouvé :", level);
            //     return;
            // }
            this.currentLevelConfig = generateLevel(level);
            console.log("Niveau chargé :", this.currentLevelConfig);

            // Réinitialiser les envahisseurs
            this.aliens = [];
            this.initAliens();
        }

        initAliens() {
            const { rows, cols, alienSpacing, alienWidth, alienHeight, formation } = this.currentLevelConfig;

            switch (formation) {
            case "grid":
                // Disposition en grille
                for (let row = 0; row < rows; row++) {
                    for (let col = 0; col < cols; col++) {
                        const x = col * (alienWidth + alienSpacing) + alienSpacing;
                        const y = row * (alienHeight + alienSpacing) + alienSpacing;
                        const alien = new Alien(x, y, this.currentLevelConfig.alienSpeed);
                        this.aliens.push(alien);
                    }
                }
            break;
        
            case "V":
                // Disposition en V
                for (let row = 0; row < rows; row++) {
                    const numAliens = cols - row; // Réduire le nombre d'envahisseurs par rangée
                    for (let col = 0; col < numAliens; col++) {
                        const x = (col + row / 2) * (alienWidth + alienSpacing) + alienSpacing;
                        const y = row * (alienHeight + alienSpacing) + alienSpacing;
                        const alien = new Alien(x, y, this.currentLevelConfig.alienSpeed);
                        this.aliens.push(alien);
                    }
                }
            break;

            case "circle":
                // Disposition en cercle
                const centerX = this.canvas.width / 2;
                const centerY = 20; // Ajustez cette valeur pour éviter que les aliens ne soient trop bas
                const radius = Math.min(15, this.canvas.width / 2 - 50); // Limiter le rayon pour rester dans le canvas
                for (let i = 0; i < rows * cols; i++) {
                    const angle = (i / (rows * cols)) * 2 * Math.PI;
                    const x = centerX + Math.cos(angle) * radius;
                    const y = centerY + Math.sin(angle) * radius;
                    const alien = new Alien(x, y, this.currentLevelConfig.alienSpeed);
                    this.aliens.push(alien);
                }
            break;

            default:
                console.error("Formation non reconnue :", formation);
            }
        }

        setupControls() {
            document.addEventListener("keydown", (event) => {
                if (event.key === "ArrowLeft") this.player.move("left", this.canvas.width);
                if (event.key === "ArrowRight") this.player.move("right", this.canvas.width);
                if (event.key === " ") this.player.shoot(this.bullets);
            });
        }

        update(currentTime) {
            if (!this.isRunning) return;

            this.bullets.forEach((bullet, index) => {
                bullet.update();

                if (bullet.y < 0 || bullet.y > this.canvas.height) {
                    this.bullets.splice(index, 1);
                }

                if (!bullet.isPlayerBullet && isColliding(bullet, this.player)) {
                    // Add sound
                    const hitSound = new Audio('../js/sounds/hit.mp3');
                    hitSound.play();

                    this.bullets.splice(index, 1);
                    this.player.lives--;
                    this.updateLivesDisplay();

                    this.player.x = this.canvas.width / 2 - this.player.width / 2;
                    this.player.isInvincible = true;
                    setTimeout(() => this.player.isInvincible = false, 2000);

                    if (this.player.lives <= 0) {
                        this.isRunning = false;
                        this.gameOver();
                    }
                }

                if (bullet.isPlayerBullet) {
                    this.aliens.forEach((alien, alienIndex) => {
                        if (isColliding(bullet, alien)) {
                            this.bullets.splice(index, 1);
                            this.aliens.splice(alienIndex, 1);
                            this.score += 10;
                            this.updateScore();

                            if (this.aliens.length === 0) {
                                this.nextLevel(); // Passer au niveau suivant
                                return; // Arrêter la mise à jour pour éviter des erreurs
                            }
                        }
                    });
                }
            });

            if (this.aliens.length === 0) {
                this.nextLevel();
            }

            this.aliens.forEach((alien) => {
                alien.move(this.canvas.width);
                if (alien.y + alien.height >= this.canvas.height - 10) {
                    this.isRunning = false;
                    this.gameOver();
                    return;
                }
                alien.shoot(this.bullets, currentTime);
            });

            if (this.shootFrequencyIncrease < 5) {
                this.shootFrequencyIncrease *= 1.001;
            }
        }

        updateScore() {
            if (scoreElement) scoreElement.textContent = this.score;
        }

        updateLevel() {
            if (levelElement) levelElement.textContent = this.level;
        }

        updateLivesDisplay() {
            if (livesElement) livesElement.textContent = this.player.lives;
        }

        draw() {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
            this.player.draw(this.ctx);
            this.bullets.forEach((bullet) => bullet.draw(this.ctx));
            this.aliens.forEach((alien) => alien.draw(this.ctx));
        }

        nextLevel() {
            this.level++;
            console.log("Passage au niveau", this.level);

            this.loadLevel(this.level);
            this.updateLevel();
            this.bullets = [];
            this.player.x = this.canvas.width / 2 - this.player.width / 2;
        }

        gameOver() {
            this.isRunning = false;
            endGame(this);
        }

        start() {
            const loop = (currentTime) => {
                if (this.isRunning) {
                    this.update(currentTime);
                    this.draw();
                    requestAnimationFrame(loop);
                }
            };
            requestAnimationFrame(loop);
        }
    }

    /*********************************************************************************/
    /*                                CLASS PLAYER                                   */
    /*********************************************************************************/
    class Player {
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.width = 8;
            this.height = 4;
            this.speed = 5;
            this.image = new Image();
            this.image.src = "/js/sprites/spaceship.png";
            this.lastShootTime = 0;
            this.shootCooldown = 300;
            this.lives = 3;
            this.isInvincible = false;
        }

        move(direction, canvasWidth) {
            if (direction === "left" && this.x > 0) {
                this.x -= this.speed;
            } else if (direction === "right" && this.x + this.width < canvasWidth) {
                this.x += this.speed;
            }
        }

        shoot(bulletsArray) {
            const currentTime = Date.now();
            if (currentTime - this.lastShootTime >= this.shootCooldown) {
                const bullet = new Bullet(this.x + this.width / 2, this.y, -5, true);
                bulletsArray.push(bullet);
                this.lastShootTime = currentTime;
            }
        }

        draw(ctx) {
            ctx.globalAlpha = this.isInvincible ? 0.5 : 1.0;
            ctx.drawImage(this.image, this.x, this.y, this.width, this.height);
        }
    }

    /*********************************************************************************/
    /*                                  CLASS ALIEN                                  */
    /*********************************************************************************/
    class Alien {
        constructor(x, y, speed) {
            this.x = x;
            this.y = y;
            this.width = 6;
            this.height = 4;
            this.speed = speed;
            this.direction = 1;
            this.lastShootTime = 0;
            this.shootCooldown = 1000;
            this.images = [
                "/js/sprites/green.png",
                "/js/sprites/red.png",
                "/js/sprites/yellow.png",
            ];
            this.image = new Image();
            this.image.src = this.images[Math.floor(Math.random() * this.images.length)];
            this.isImageLoaded = false;
            this.image.onload = () => this.isImageLoaded = true;
        }

        move(canvasWidth) {
            this.x += this.speed * this.direction;
            if (this.x <= 0 || this.x + this.width >= canvasWidth) {
                this.direction *= -1;
                this.y += 20;
            }
        }

        shoot(bulletsArray, currentTime) {
            if (currentTime - this.lastShootTime > this.shootCooldown && Math.random() < 0.0001) {
                const bullet = new Bullet(this.x + this.width / 2, this.y + this.height, 2, false);
                bulletsArray.push(bullet);
                this.lastShootTime = currentTime;
            }
        }

        draw(ctx) {
            if (this.isImageLoaded) {
                ctx.drawImage(this.image, this.x, this.y, this.width, this.height);
            }
        }
    }

    /*********************************************************************************/
    /*                                CLASS BULLET                                   */
    /*********************************************************************************/
    class Bullet {
        constructor(x, y, speed, isPlayerBullet = true) {
            this.x = x;
            this.y = y;
            this.speed = speed;
            this.isPlayerBullet = isPlayerBullet;
            this.width = 1;
            this.height = 2;
            this.color = isPlayerBullet ? "yellow" : "red";
            this.playShootSound();
        }

        playShootSound() {
            if(isSoundEnabled) {
                const shootSound = new Audio(`../js/sounds/${this.isPlayerBullet ? 'lazer1' : 'lazer2'}.mp3`);
                shootSound.play();
            }
        }

        update() {
            this.y += this.speed;
        }

        draw(ctx) {
            ctx.fillStyle = this.color;
            ctx.fillRect(this.x, this.y, this.width, this.height);
        }
    }

    /*********************************************************************************/
    /*                                HELPER FUNCTIONS                               */
    /*********************************************************************************/
    function isColliding(rect1, rect2) {
        return (
            rect1.x < rect2.x + rect2.width &&
            rect1.x + rect1.width > rect2.x &&
            rect1.y < rect2.y + rect2.height &&
            rect1.y + rect1.height > rect2.y
        );
    }

    function endGame(gameInstance) {
        saveScore(gameInstance.score, gameInstance.team);
        alert(`💀 Game Over ! Your score is ${gameInstance.score} 💀`);
        if (scoreFinal) scoreFinal.textContent = gameInstance.score;
        if (teamChoice) {
            teamChoice.textContent = gameInstance.team === 1 ? "Team abeille" : "Team canard";
            teamChoice.classList.add(gameInstance.team === 1 ? "team-bee" : "team-duck");
        }
        resetGame(gameInstance);
    }

    function saveScore(score, team) {
        const teamName = team === 1 ? 'abeille' : 'canard';
        fetch('/game/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ score: score, team: teamName })
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response error: ' + response.statusText);
            return response.json();
        })
        .then(data => console.log('Score saved successfully:', data))
        .catch(error => console.error('Error saving score:', error));
    }

    function resetGame(gameInstance) {
        gameInstance.player.lives = 3;
        gameInstance.score = 0;
        gameInstance.level = 1;
        if (scoreElement) scoreElement.textContent = gameInstance.score;
        if (levelElement) levelElement.textContent = gameInstance.level;
        if (livesElement) livesElement.textContent = gameInstance.player.lives;
        if (gameInfoContainer) gameInfoContainer.style.display = "none";
        if (gameContainer) gameContainer.style.display = "flex";
    }

    function toggleMusic() {
        isSoundEnabled = !isSoundEnabled;
        localStorage.setItem('Music', isSoundEnabled);
        if (isSoundEnabled) {
            startMusicLoop();
            if (musicButton) musicButton.innerHTML = '&#128266;';
        } else {
            audio1.pause();
            if (musicButton) musicButton.innerHTML = '&#128263;';
        }
    }

    function startMusicLoop() {
        isPaused = false;
        audio1.play();
        audio1.addEventListener('ended', () => {
            if (!isPaused) {
                audio1.currentTime = 0;
                audio1.play();
            }
        });
    }

    function startGame(selectedTeam) {
        team = selectedTeam;
        gameTeamChoice.style.display = "none";
        gameContainer.style.display = "none";
        gameInfoContainer.style.display = "block";
        const game = new Game("canvasId");
        game.start();
    }

    function generateLevel(level) {
        const baseRows = 3;
        const baseCols = 5;
        const baseSpeed = 0.5;
        const baseSpacing = 10;
    
        // Calculer les paramètres avec des limites maximales
        const rows = Math.min(baseRows + Math.floor(level / 3), 10); // Max 10 rangées
        const cols = Math.min(baseCols + Math.floor(level / 2), 15); // Max 15 colonnes
        const alienSpeed = Math.min(baseSpeed + (level * 0.1), 3); // Max vitesse de 3
        const alienSpacing = Math.max(5, baseSpacing - (level * 0.5)); // Min espacement de 5
    
        const formations = ["grid", "V", "circle"];
        const formation = formations[Math.floor(Math.random() * formations.length)];
    
        return {
            level: level,
            rows: rows,
            cols: cols,
            alienSpeed: alienSpeed,
            alienSpacing: alienSpacing,
            alienWidth: 6,
            alienHeight: 4,
            formation: formation,
        };
    }

    if (isSoundEnabled) { 
        startMusicLoop();
    }
});

// const levels = [
//     {
//         level: 1,
//         rows: 3,
//         cols: 5,
//         alienSpeed: 0.4,
//         alienSpacing: 6,
//         alienWidth: 6,
//         alienHeight: 4,
//         formation: "grid",
//     },
//     {
//         level: 2,
//         rows: 4,
//         cols: 6,
//         alienSpeed: 0.5,
//         alienSpacing: 5,
//         alienWidth: 6,
//         alienHeight: 4,
//         formation: "V",
//     },
//     {
//         level: 3,
//         rows: 3,
//         cols: 3,
//         alienSpeed: 0.6,
//         alienSpacing: 3,
//         alienWidth: 6,
//         alienHeight: 4,
//         formation: "circle",
//     }
// ];