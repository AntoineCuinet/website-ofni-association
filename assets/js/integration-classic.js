import * as monaco from 'monaco-editor';

// DOM Elements
let outputText, runBtn;
let editor;
let pyodide = null;

document.addEventListener('DOMContentLoaded', async () => {
    // Initialize DOM references
    outputText = document.getElementById('output-text');
    runBtn = document.getElementById('run-btn');

    // Initialize Monaco Editor
    const editorContainer = document.getElementById('code-editor-container');
    if (editorContainer) {
        editor = monaco.editor.create(editorContainer, {
            value: [
                '# Solution pour Two Sum',
                'def twoSum(nums, target):',
                '    seen = {}',
                '    for i, num in enumerate(nums):',
                '        complement = target - num',
                '        if complement in seen:',
                '            return [seen[complement], i]',
                '        seen[num] = i',
                '    return []',
                '',
                '# Test Case',
                'nums = [2, 7, 11, 15]',
                'target = 9',
                'print(twoSum(nums, target))'
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
            runBtn.innerText = "Soumettre";
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

    // Event Listeners
    if (runBtn) {
        runBtn.addEventListener('click', handleRun);
    }
});

function addToLog(msg) {
    if(outputText) outputText.innerText += msg + "\n";
}

// --- Python Standard Output Handling ---
function handleStdout(msg) {
    addToLog(msg);
}

// --- Execution ---
async function handleRun() {
    // Clear Output
    if(outputText) outputText.innerText = "";
    
    // Get code from Monaco
    const code = editor.getValue();
    
    // Redirect stdout
    pyodide.setStdout({ batched: (msg) => addToLog(msg) });
    pyodide.setStderr({ batched: (msg) => addToLog("[STDERR] " + msg) });

    try {
        await pyodide.runPythonAsync(code);
    } catch (err) {
        addToLog("Erreur: " + err);
        console.error(err);
    }
}
