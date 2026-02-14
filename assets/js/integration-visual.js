import * as monaco from 'monaco-editor';

// --- Configuration ---
const GRID_SIZE = 10;
const TILE_SIZE = 40;
const CANVAS_WIDTH = 400;
const CANVAS_HEIGHT = 400;
const ANIMATION_SPEED = 200; // ms per step

// Game State
let robotPos = { x: 0, y: 0 };
let startPos = { x: 1, y: 1 };
let goalPos = { x: 8, y: 8 };
let moveQueue = [];
let isRunning = false;
let pyodide = null;

// DOM Elements
let canvas, ctx, outputText, runBtn, statusMsg;
let editor;

// 0 = Empty, 1 = Wall
const map = [
    [1,1,1,1,1,1,1,1,1,1],
    [1,0,0,0,1,0,0,0,0,1],
    [1,0,1,0,1,0,1,1,0,1],
    [1,0,1,0,0,0,0,0,0,1],
    [1,0,1,1,1,1,1,1,0,1],
    [1,0,0,0,0,0,0,1,0,1],
    [1,0,1,1,0,1,0,1,0,1],
    [1,0,0,1,0,1,0,1,0,1],
    [1,1,0,0,0,1,0,0,0,1],
    [1,1,1,1,1,1,1,1,1,1]
];

document.addEventListener('DOMContentLoaded', async () => {
    // Initialize DOM references
    canvas = document.getElementById('game-canvas');
    ctx = canvas.getContext('2d');
    outputText = document.getElementById('output-text');
    runBtn = document.getElementById('run-btn');
    statusMsg = document.getElementById('status-message');

    // Initialize Monaco Editor
    const editorContainer = document.getElementById('code-editor-container');
    if (editorContainer) {
        editor = monaco.editor.create(editorContainer, {
            value: [
                '# Guidez le robot vers la sortie !',
                'def solve():',
                '    # Votre code ici...',
                '    for i in range(3):',
                '        robot.right()',
                '    robot.down()',
                '',
                'solve()'
            ].join('\n'),
            language: 'python',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            scrollBeyondLastLine: false,
            fontSize: 14,
            fontFamily: "'Consolas', 'Monaco', monospace"
        });
    }

    // Load Pyodide
    try {
        if (typeof loadPyodide === 'function') {
            pyodide = await loadPyodide();
            runBtn.innerText = "Exécuter le code";
            runBtn.disabled = false;
            console.log("Pyodide loaded!");
        } else {
            console.error("Pyodide script not loaded.");
            runBtn.innerText = "Erreur (CDN Manquant)";
        }
    } catch (err) {
        console.error(err);
        runBtn.innerText = "Erreur chargement Python";
    }

    // Initial Draw
    resetGame();

    // Event Listeners
    runBtn.addEventListener('click', handleRun);
});

// --- Game Logic ---
function resetGame() {
    robotPos = { ...startPos };
    moveQueue = [];
    isRunning = false;
    draw();
    updateStatus("Prêt", "waiting");
    outputText.innerText = "";
}

function draw() {
    // Clear
    ctx.fillStyle = "#222";
    ctx.fillRect(0, 0, CANVAS_WIDTH, CANVAS_HEIGHT);

    // Draw Map
    for(let y=0; y<GRID_SIZE; y++) {
        for(let x=0; x<GRID_SIZE; x++) {
            if(map[y][x] === 1) {
                ctx.fillStyle = "#555"; // Wall
                ctx.fillRect(x*TILE_SIZE, y*TILE_SIZE, TILE_SIZE, TILE_SIZE);
                ctx.strokeStyle = "#333";
                ctx.strokeRect(x*TILE_SIZE, y*TILE_SIZE, TILE_SIZE, TILE_SIZE);
            } else {
                ctx.fillStyle = "#222"; // Floor
                ctx.fillRect(x*TILE_SIZE, y*TILE_SIZE, TILE_SIZE, TILE_SIZE);
                ctx.strokeStyle = "#333";
                ctx.strokeRect(x*TILE_SIZE, y*TILE_SIZE, TILE_SIZE, TILE_SIZE);
            }
        }
    }

    // Draw Goal
    ctx.fillStyle = "rgba(52, 211, 153, 0.5)";
    ctx.fillRect(goalPos.x*TILE_SIZE, goalPos.y*TILE_SIZE, TILE_SIZE, TILE_SIZE);
    ctx.strokeStyle = "#34d399";
    ctx.strokeRect(goalPos.x*TILE_SIZE, goalPos.y*TILE_SIZE, TILE_SIZE, TILE_SIZE);

    // Draw Robot
    ctx.fillStyle = "#3b82f6";
    ctx.beginPath();
    ctx.arc(
        robotPos.x * TILE_SIZE + TILE_SIZE/2,
        robotPos.y * TILE_SIZE + TILE_SIZE/2,
        TILE_SIZE/3, 0, 2*Math.PI
    );
    ctx.fill();
}

function updateStatus(text, type) {
    if(!statusMsg) return;
    statusMsg.innerText = text;
    statusMsg.className = `status-${type}`;
}

function addToLog(msg) {
    if(outputText) outputText.innerText += msg + "\n";
}

// --- Robot API (Exposed to Python) ---
const robotAPI = {
    up: () => { moveQueue.push({dx: 0, dy: -1}); },
    down: () => { moveQueue.push({dx: 0, dy: 1}); },
    left: () => { moveQueue.push({dx: -1, dy: 0}); },
    right: () => { moveQueue.push({dx: 1, dy: 0}); },
    log: (msg) => { addToLog("[PYTHON] " + msg); }
};

// --- Execution ---
async function handleRun() {
    if(isRunning) return;
    resetGame();
    
    // Get code from Monaco
    const code = editor.getValue();
    updateStatus("Calcul...", "running");

    // Inject our API into Python
    const pySetup = `
import js
from js import robotAPI

class Robot:
    def up(self): robotAPI.up()
    def down(self): robotAPI.down()
    def left(self): robotAPI.left()
    def right(self): robotAPI.right()
    def log(self, msg): robotAPI.log(msg)

robot = Robot()
`;
    
    try {
        // Register JS object in Pyodide namespace
        window.robotAPI = robotAPI;
        
        await pyodide.runPythonAsync(pySetup + "\n" + code);
        
        // Start Animation
        startAnimation();
        
    } catch (err) {
        updateStatus("Erreur Python", "error");
        addToLog("Erreur: " + err);
        console.error(err);
    }
}

function startAnimation() {
    if (moveQueue.length === 0) {
        checkWinCondition();
        return;
    }

    isRunning = true;
    updateStatus("Exécution...", "running");

    let index = 0;
    const interval = setInterval(() => {
        if (index >= moveQueue.length) {
            clearInterval(interval);
            isRunning = false;
            checkWinCondition();
            return;
        }

        const move = moveQueue[index];
        const nextX = robotPos.x + move.dx;
        const nextY = robotPos.y + move.dy;

        // Collision Check
        if (
            nextX >= 0 && nextX < GRID_SIZE &&
            nextY >= 0 && nextY < GRID_SIZE &&
            map[nextY][nextX] === 0
        ) {
            robotPos.x = nextX;
            robotPos.y = nextY;
        } else {
            addToLog(`Collision en (${nextX}, ${nextY}) !`);
            // Optional: Stop on collision
        }

        draw();
        index++;
    }, ANIMATION_SPEED);
}

function checkWinCondition() {
    if (robotPos.x === goalPos.x && robotPos.y === goalPos.y) {
        updateStatus("SUCCÈS !", "success");
        addToLog("Objectif atteint !");
    } else {
        updateStatus("Échec - Destination non atteinte", "error");
    }
}
