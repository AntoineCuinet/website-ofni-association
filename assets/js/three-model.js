import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

console.log('here');

// Containers for duck and bee
const duck_container = document.getElementById('duck-model-container');
const bee_container = document.getElementById('bee-model-container');

// Scene, camera, and renderer for duck
const duckScene = new THREE.Scene();
const duckCamera = new THREE.PerspectiveCamera(75, duck_container.offsetWidth / duck_container.offsetHeight, 0.1, 1000);
const duckRenderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
duckRenderer.setSize(duck_container.offsetWidth, duck_container.offsetHeight);
duckRenderer.setClearColor('#111127', 0);
duck_container.appendChild(duckRenderer.domElement);

// Scene, camera, and renderer for bee
const beeScene = new THREE.Scene();
const beeCamera = new THREE.PerspectiveCamera(75, bee_container.offsetWidth / bee_container.offsetHeight, 0.1, 1000);
const beeRenderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
beeRenderer.setSize(bee_container.offsetWidth, bee_container.offsetHeight);
beeRenderer.setClearColor('#111127', 0);
bee_container.appendChild(beeRenderer.domElement);

// Light for both scenes
const light = new THREE.AmbientLight(0xffffff, 4);
duckScene.add(light.clone());
beeScene.add(light.clone());

// Loader for models
const loader = new GLTFLoader();

// Load and animate the duck
loader.load('/build/models/yellow-rubber-duck/source/yellow_rubber_duck/Rubbish_Duck.gltf', (gltf) => {
    const duckModel = gltf.scene;
    duckModel.scale.set(3, 3, 3);
    duckScene.add(duckModel);

    duckModel.position.set(0, 0, 0);
    duckCamera.position.set(4, 3, 5);
    duckCamera.lookAt(0, 0, 0);

    let c = 0;
    let duckRotation = 0.005;
    function animateDuck() {
        c++;
        if (c == 100) {
            c = 0;
            duckRotation = -duckRotation;
        }
        requestAnimationFrame(animateDuck);
        duckModel.rotation.y += duckRotation;
        duckModel.position.y += duckRotation;
        duckRenderer.render(duckScene, duckCamera);
    }
    animateDuck();
});

// Load and animate the bee
loader.load('/build/models/cartoon_bee/scene.gltf', (gltf) => {
    const beeModel = gltf.scene;
    beeModel.scale.set(100, 100, 100);
    beeScene.add(beeModel);

    beeModel.position.set(0, 0, 0);
    beeCamera.position.set(4, 3, 5);
    beeCamera.lookAt(0, 0, 0);

    let c = 0;
    let beeRotation = 0.005;
    function animateBee() {
        c++;
        if (c == 100) {
            c = 0;
            beeRotation = -beeRotation;
        }
        requestAnimationFrame(animateBee);
        beeModel.rotation.y += beeRotation;
        beeModel.position.y += beeRotation;
        beeRenderer.render(beeScene, beeCamera);
    }
    animateBee();
});

// Set camera positions
duckCamera.position.z = 5;
beeCamera.position.z = 5;
