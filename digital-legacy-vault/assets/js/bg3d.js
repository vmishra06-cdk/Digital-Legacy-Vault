/**
 * Ambient Cyber 3D Background - Floating Infinity Loop Engine (Three.js)
 * Features:
 * - Parametric 3D Infinity (Lemniscate) Ribbon Tube
 * - Outer Holographic Wireframe Energy Lattice
 * - Continuous Flowing Energy Particles travelling along the Infinity Path
 * - Ambient Starlight Parallax Nodes
 * - Smooth Mouse Tilt & Float Levitation
 */
(function() {
  const container = document.getElementById('canvas-3d-bg');
  if (!container || typeof THREE === 'undefined') return;

  // Scene & Camera
  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(55, window.innerWidth / window.innerHeight, 1, 2000);
  camera.position.set(0, 0, 420);

  // WebGL Renderer
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
  renderer.setSize(window.innerWidth, window.innerHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  container.innerHTML = '';
  container.appendChild(renderer.domElement);

  // Dynamic Colored Lights
  const ambientLight = new THREE.AmbientLight(0x070b14, 1.5);
  scene.add(ambientLight);

  const cyanLight = new THREE.PointLight(0x00f0ff, 3.0, 600);
  cyanLight.position.set(200, 150, 200);
  scene.add(cyanLight);

  const purpleLight = new THREE.PointLight(0x8b5cf6, 2.5, 600);
  purpleLight.position.set(-200, -150, 100);
  scene.add(purpleLight);

  const emeraldLight = new THREE.PointLight(0x10b981, 1.8, 500);
  emeraldLight.position.set(0, 0, 250);
  scene.add(emeraldLight);

  // ==========================================
  // PARAMETRIC 3D INFINITY (LEMNISCATE) CURVE
  // ==========================================
  class InfinityCurve extends THREE.Curve {
    constructor(scaleX = 160, scaleY = 80, scaleZ = 50) {
      super();
      this.scaleX = scaleX;
      this.scaleY = scaleY;
      this.scaleZ = scaleZ;
    }

    getPoint(t, optionalTarget = new THREE.Vector3()) {
      const u = t * Math.PI * 2;
      // Parametric 3D Infinity Loop (Figure-Eight in X-Y with Z-twist depth)
      const x = this.scaleX * Math.sin(u);
      const y = this.scaleY * Math.sin(2 * u);
      const z = this.scaleZ * Math.cos(u);
      return optionalTarget.set(x, y, z);
    }
  }

  const infinityCurve = new InfinityCurve(160, 80, 50);
  const infinityGroup = new THREE.Group();

  // 1. Core Glowing Infinity Tube
  const tubeGeo = new THREE.TubeGeometry(infinityCurve, 200, 3.2, 16, true);
  const tubeMat = new THREE.MeshStandardMaterial({
    color: 0x00f0ff,
    emissive: 0x0077aa,
    emissiveIntensity: 0.65,
    metalness: 0.85,
    roughness: 0.15,
    transparent: true,
    opacity: 0.85
  });
  const tubeMesh = new THREE.Mesh(tubeGeo, tubeMat);
  infinityGroup.add(tubeMesh);

  // 2. Outer Holographic Hexagonal Wireframe Cage
  const cageGeo = new THREE.TubeGeometry(infinityCurve, 90, 7.5, 6, true);
  const cageMat = new THREE.MeshBasicMaterial({
    color: 0x8b5cf6,
    wireframe: true,
    transparent: true,
    opacity: 0.22
  });
  const cageMesh = new THREE.Mesh(cageGeo, cageMat);
  infinityGroup.add(cageMesh);

  // 3. Flowing Energy Stream Particles along the Infinity Path
  const streamParticleCount = 180;
  const streamGeo = new THREE.BufferGeometry();
  const streamPositions = new Float32Array(streamParticleCount * 3);
  const streamColors = new Float32Array(streamParticleCount * 3);
  const streamOffsets = new Float32Array(streamParticleCount);

  const streamColor1 = new THREE.Color(0x00f0ff);
  const streamColor2 = new THREE.Color(0x38ef7d);
  const streamColor3 = new THREE.Color(0x8b5cf6);

  for (let i = 0; i < streamParticleCount; i++) {
    streamOffsets[i] = i / streamParticleCount;
    const pt = infinityCurve.getPoint(streamOffsets[i]);
    streamPositions[i * 3] = pt.x;
    streamPositions[i * 3 + 1] = pt.y;
    streamPositions[i * 3 + 2] = pt.z;

    const ratio = i / streamParticleCount;
    const col = ratio < 0.5 
      ? streamColor1.clone().lerp(streamColor2, ratio * 2) 
      : streamColor2.clone().lerp(streamColor3, (ratio - 0.5) * 2);

    streamColors[i * 3] = col.r;
    streamColors[i * 3 + 1] = col.g;
    streamColors[i * 3 + 2] = col.b;
  }

  streamGeo.setAttribute('position', new THREE.BufferAttribute(streamPositions, 3));
  streamGeo.setAttribute('color', new THREE.BufferAttribute(streamColors, 3));

  const streamMat = new THREE.PointsMaterial({
    size: 4.5,
    vertexColors: true,
    transparent: true,
    opacity: 0.9,
    blending: THREE.AdditiveBlending
  });
  const streamParticles = new THREE.Points(streamGeo, streamMat);
  infinityGroup.add(streamParticles);

  scene.add(infinityGroup);

  // ==========================================
  // DISTANT AMBIENT STARFIELD NODES
  // ==========================================
  const bgParticleCount = 150;
  const bgGeo = new THREE.BufferGeometry();
  const bgPositions = new Float32Array(bgParticleCount * 3);
  const bgColors = new Float32Array(bgParticleCount * 3);

  const starColors = [
    new THREE.Color(0x00f0ff),
    new THREE.Color(0x8b5cf6),
    new THREE.Color(0x38ef7d),
    new THREE.Color(0x3b82f6)
  ];

  for (let i = 0; i < bgParticleCount * 3; i += 3) {
    bgPositions[i] = (Math.random() - 0.5) * 1000;
    bgPositions[i + 1] = (Math.random() - 0.5) * 900;
    bgPositions[i + 2] = (Math.random() - 0.5) * 800 - 150;

    const c = starColors[Math.floor(Math.random() * starColors.length)];
    bgColors[i] = c.r;
    bgColors[i + 1] = c.g;
    bgColors[i + 2] = c.b;
  }

  bgGeo.setAttribute('position', new THREE.BufferAttribute(bgPositions, 3));
  bgGeo.setAttribute('color', new THREE.BufferAttribute(bgColors, 3));

  const bgMat = new THREE.PointsMaterial({
    size: 2.8,
    vertexColors: true,
    transparent: true,
    opacity: 0.55
  });
  const bgParticles = new THREE.Points(bgGeo, bgMat);
  scene.add(bgParticles);

  // ==========================================
  // INTERACTIVE PARALLAX & ANIMATION LOOP
  // ==========================================
  let mouseX = 0, mouseY = 0;
  let targetMouseX = 0, targetMouseY = 0;

  window.addEventListener('mousemove', (e) => {
    targetMouseX = (e.clientX - window.innerWidth / 2) * 0.15;
    targetMouseY = (e.clientY - window.innerHeight / 2) * 0.15;
  });

  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  let time = 0;
  const tempVec = new THREE.Vector3();

  function animate() {
    requestAnimationFrame(animate);
    time += 0.012;

    // Smooth mouse interpolation
    mouseX += (targetMouseX - mouseX) * 0.04;
    mouseY += (targetMouseY - mouseY) * 0.04;

    // 1. Floating Levitation & Hypnotic Rotation of the Infinity Symbol
    infinityGroup.rotation.y = time * 0.25;
    infinityGroup.rotation.x = Math.sin(time * 0.4) * 0.22 + (mouseY * 0.002);
    infinityGroup.rotation.z = Math.cos(time * 0.3) * 0.15 + (mouseX * 0.002);

    // Floating bobbing
    infinityGroup.position.y = Math.sin(time * 0.8) * 16;
    infinityGroup.position.x = Math.cos(time * 0.5) * 10;

    // 2. Animate Stream Particles Racing Along the Infinity Curve
    const posAttr = streamGeo.attributes.position;
    for (let i = 0; i < streamParticleCount; i++) {
      let progress = (streamOffsets[i] + time * 0.12) % 1.0;
      infinityCurve.getPoint(progress, tempVec);
      posAttr.setXYZ(i, tempVec.x, tempVec.y, tempVec.z);
    }
    posAttr.needsUpdate = true;

    // 3. Subtle background particles drifting
    bgParticles.rotation.y += 0.0006;
    bgParticles.rotation.x += 0.0003;

    // 4. Parallax Camera Movement
    camera.position.x += (mouseX - camera.position.x) * 0.03;
    camera.position.y += (-mouseY - camera.position.y) * 0.03;
    camera.lookAt(scene.position);

    // 5. Breathing Lights
    cyanLight.intensity = 3.0 + Math.sin(time * 2) * 0.6;
    purpleLight.intensity = 2.5 + Math.cos(time * 2) * 0.5;

    renderer.render(scene, camera);
  }

  animate();
})();
