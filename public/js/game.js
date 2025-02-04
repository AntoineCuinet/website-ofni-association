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
            this.initAliens();
            this.setupControls();
            this.lastTime = 0;
            this.shootFrequencyIncrease = 1.01;
        }

        initAliens() {
            const rows = 5;
            const cols = 10;
            const alienSpacing = 2;
            const alienWidth = 6;
            const alienHeight = 4;

            for (let row = 0; row < rows; row++) {
                for (let col = 0; col < cols; col++) {
                    const x = col * (alienWidth + alienSpacing) + alienSpacing;
                    const y = row * (alienHeight + alienSpacing) + alienSpacing;
                    this.aliens.push(new Alien(x, y));
                }
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
            this.updateLevel();
            this.aliens = [];
            const alienSpeed = 1 + this.level * 0.05;
            const rows = 5;
            const cols = 10;
            const alienSpacing = 2;
            const alienWidth = 6;
            const alienHeight = 4;

            for (let row = 0; row < rows; row++) {
                for (let col = 0; col < cols; col++) {
                    const x = col * (alienWidth + alienSpacing) + alienSpacing;
                    const y = row * (alienHeight + alienSpacing) + alienSpacing;
                    const alien = new Alien(x, y);
                    alien.speed = alienSpeed;
                    this.aliens.push(alien);
                }
            }
        }

        gameOver() {
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
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.width = 6;
            this.height = 4;
            this.speed = 0.5;
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
        gameInfoContainer.style.display = "block";
        const game = new Game("canvasId");
        game.start();
    }

    if (isSoundEnabled) { 
        startMusicLoop();
    }
});