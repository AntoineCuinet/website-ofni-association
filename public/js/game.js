"use strict";

document.addEventListener("DOMContentLoaded", function() {

    const gameContainer = document.getElementsByClassName("game-container")[0];
    const gameInfoContainer = document.getElementsByClassName("game-info-container")[0];
    const btnStart = document.getElementById("btn-start-game");
    const scoreElement = document.getElementById("score-text");
    const scoreFinal = document.getElementById("score");

    btnStart.addEventListener("click", function() {
        gameContainer.style.display = "none";
        gameInfoContainer.style.display = "block";
        const game = new Game("canvasId");
        game.start();
    });

    class Game {
        constructor(canvasId) {
            this.canvas = document.getElementById(canvasId);
            this.ctx = this.canvas.getContext("2d", { antialias: false });
            // this.ctx.imageSmoothingEnabled = false; // Désactive l'anticrénelage pour les images
            // this.ctx.imageSmoothingQuality = 'low'; // Qualité de lissage minimale
            this.player = new Player(this.canvas.width / 2 - 20, this.canvas.height - 10);
            this.aliens = [];
            this.bullets = [];
            this.isRunning = true;
            this.score = 0;
            this.initAliens();
            this.setupControls();
            this.lastTime = 0;
            this.shootFrequencyIncrease = 1.01;
        }
      
        /**
         * Initializes the grid of aliens.
         */
        initAliens() {
            const rows = 5; // Number of rows of aliens
            const cols = 10; // Number of columns of aliens
            const alienSpacing = 2; // Space between aliens
            const alienWidth = 6;
            const alienHeight = 4;

            for (let row = 0; row < rows; row++) {
                for (let col = 0; col < cols; col++) {
                    const x = col * (alienWidth + alienSpacing) + alienSpacing;
                    const y = row * (alienHeight + alienSpacing) + alienSpacing; // Commence en haut
                    this.aliens.push(new Alien(x, y));
                }
            }
        }


        /**
         * Sets up controls for player actions.
         */
        setupControls() {
            document.addEventListener("keydown", (event) => {
            if (event.key === "ArrowLeft") this.player.move("left", this.canvas.width);
            if (event.key === "ArrowRight") this.player.move("right", this.canvas.width);
            if (event.key === " ") this.player.shoot(this.bullets); // Space bar to shoot
            });
        }
        
      
        /**
         * Updates the state of the game elements.
         */
        update(currentTime) {
            // Update player bullets
            this.bullets.forEach((bullet, index) => {
                bullet.update();

                // Remove bullets that go off-screen
                if (bullet.y < 0 || bullet.y > this.canvas.height) {
                    this.bullets.splice(index, 1);
                }

                // Check for collisions with aliens
                if (bullet.isPlayerBullet) {
                    this.aliens.forEach((alien, alienIndex) => {
                        if (isColliding(bullet, alien)) {
                            this.bullets.splice(index, 1); // Remove bullet
                            this.aliens.splice(alienIndex, 1); // Remove alien

                            this.score += 10;
                            this.updateScore();
                        }
                    });
                }
            });

            // Move aliens
            this.aliens.forEach((alien) => alien.move(this.canvas.width));

            // Check for game over condition (aliens reaching the bottom)
            this.aliens.forEach((alien) => {
                if (alien.y + alien.height >= this.canvas.height - 10) {
                    this.isRunning = false; // End the game
                    alert("💀 Game Over ! Your score is " + this.score + " 💀");

                    gameInfoContainer.style.display = "none";
                    scoreFinal.textContent = this.score;
                    gameContainer.style.display = "flex";
                }
                alien.shoot(this.bullets, currentTime);
            });
            if (this.shootFrequencyIncrease < 5) {
                this.shootFrequencyIncrease *= 1.001;
            }
        }

        updateScore() {
            scoreElement.textContent = this.score; // Update the score text
        }
      
        /**
         * Draws all elements on the canvas.
         */
        draw() {
            this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height); // Clear the canvas

            this.player.draw(this.ctx); // Draw the player
            this.bullets.forEach((bullet) => bullet.draw(this.ctx)); // Draw bullets
            this.aliens.forEach((alien) => alien.draw(this.ctx)); // Draw aliens
        }
      

        /**
         * Starts the game loop.
         */
        start() {
            // Main game loop
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


    class Player {
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.width = 8;
            this.height = 4;
            this.speed = 5;
            this.image = new Image();
            this.image.src = "/js/sprites/Alien1.jpg";
            this.lastShootTime = 0;
            this.shootCooldown = 300;
        }
      
        /**
         * Moves the player left or right based on the direction.
         * @param {string} direction - 'left' or 'right'
         * @param {number} canvasWidth - Width of the canvas to restrict movement
         */
        move(direction, canvasWidth) {
            // Move left or right
            if (direction === "left" && this.x > 0) {
                this.x -= this.speed; // Move left
            } else if (direction === "right" && this.x + this.width < canvasWidth) {
                this.x += this.speed; // Move right
            }
        }
      
        /**
         * Creates a new bullet and adds it to the bullets array.
         * @param {Array} bulletsArray - Array to store the bullets
         */
        shoot(bulletsArray) {
            const currentTime = Date.now();
            if (currentTime - this.lastShootTime >= this.shootCooldown) {
                const bullet = new Bullet(this.x + this.width / 2, this.y, -5, true); // Bullet shoots upwards
                bulletsArray.push(bullet); // Add the bullet to the array
                this.lastShootTime = currentTime;
            }
        }
      
        /**
         * Draws the player on the canvas.
         * @param {CanvasRenderingContext2D} ctx - The drawing context of the canvas
         */
        draw(ctx) {
            ctx.drawImage(this.image, this.x, this.y, this.width, this.height);
        }
    }

      
    class Alien {
        constructor(x, y) {
            this.x = x;
            this.y = y;
            this.width = 6;
            this.height = 4;
            this.speed = 0.5;
            this.direction = 1; // 1 for moving right, -1 for moving left
            this.lastShootTime = 0; // Temps du dernier tir
            this.shootCooldown = 1000; // Intervalle initial entre les tirs (en ms)

            this.images = [
                "/js/sprites/Alien1.jpg",
                "/js/sprites/Alien2.jpg",
            ];

            const randomIndex = Math.floor(Math.random() * this.images.length);
            this.image = new Image();
            this.image.src = this.images[randomIndex];
            this.image.onload = () => {
                this.isImageLoaded = true;
            };
        }
      
        /**
         * Moves the alien horizontally. If it hits a boundary, it changes direction
         * and moves down slightly.
         * @param {number} canvasWidth - The width of the canvas for boundary detection.
         */
        move(canvasWidth) {
            this.x += this.speed * this.direction;

            // Change direction and move down if hitting canvas edges
            if (this.x <= 0 || this.x + this.width >= canvasWidth) {
                this.direction *= -1; // Reverse direction
                this.y += 20; // Move down
            }
        }

        shoot(bulletsArray, currentTime) {
            if (currentTime - this.lastShootTime > this.shootCooldown && Math.random() < 0.0001) { 
                const bullet = new Bullet(this.x + this.width / 2, this.y + this.height, 2, false); // Tir vers le bas
                bulletsArray.push(bullet);
                this.lastShootTime = currentTime;
            }
        }
      
        /**
         * Draws the alien on the canvas.
         * @param {CanvasRenderingContext2D} ctx - The drawing context of the canvas
         */
        draw(ctx) {
            if (this.isImageLoaded) {
                ctx.drawImage(this.image, this.x, this.y, this.width, this.height);
            }
        }
    }

      
    class Bullet {
        constructor(x, y, speed, isPlayerBullet = true) {
            this.x = x;
            this.y = y;
            this.speed = speed;
            this.isPlayerBullet = isPlayerBullet;
            this.width = 1;
            this.height = 2;
            this.color = isPlayerBullet ? "yellow" : "red"; // Different colors for player/enemy bullets
        }
      
        update() {
            this.y += this.speed;
        }
      
        draw(ctx) {
            ctx.fillStyle = this.color;
            ctx.fillRect(this.x, this.y, this.width, this.height);
        }
    }

      
    function isColliding(rect1, rect2) {
        return (
            rect1.x < rect2.x + rect2.width &&
            rect1.x + rect1.width > rect2.x &&
            rect1.y < rect2.y + rect2.height &&
            rect1.y + rect1.height > rect2.y
        );
    }
});