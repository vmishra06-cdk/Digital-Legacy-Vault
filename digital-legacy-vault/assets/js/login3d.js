/**
 * Advanced Interactive 3D Holographic Showcase for Login Window
 * Features 3 Distinct Procedural Cyber 3D Models:
 * 1. Vault Core (Gyroscopic Cryptographic Safe)
 * 2. Quantum Key (Zero-Knowledge Master Key & Ring Lock)
 * 3. Security Shield (Biometric Defense Matrix)
 */
(function() {
  const container = document.getElementById('login-3d-viewport');
  if (!container || typeof THREE === 'undefined') return;

  // Scene & Camera
  const scene = new THREE.Scene();
  const width = container.clientWidth || 380;
  const height = container.clientHeight || 300;
  const camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 1000);
  camera.position.set(0, 0, 7.5);

  // WebGL Renderer
  const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true, powerPreference: 'high-performance' });
  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.2;
  container.appendChild(renderer.domElement);

  // Lighting
  const ambientLight = new THREE.AmbientLight(0x0f172a, 1.8);
  scene.add(ambientLight);

  const keyLight = new THREE.PointLight(0x00f0ff, 3.5, 25);
  keyLight.position.set(4, 5, 5);
  scene.add(keyLight);

  const fillLight = new THREE.PointLight(0x8b5cf6, 2.5, 25);
  fillLight.position.set(-4, -4, -2);
  scene.add(fillLight);

  const pulseLight = new THREE.PointLight(0x10b981, 1.5, 15);
  pulseLight.position.set(0, 0, 2);
  scene.add(pulseLight);

  // Model Groups
  const models = {
    vault: new THREE.Group(),
    key: new THREE.Group(),
    shield: new THREE.Group()
  };

  let activeModelKey = 'vault';
  let targetScales = { vault: 1.0, key: 0.001, shield: 0.001 };
  let currentScales = { vault: 1.0, key: 0.001, shield: 0.001 };
  let typingBoost = 0;

  // ==========================================
  // MODEL 1: THE HOLOGRAPHIC VAULT CORE
  // ==========================================
  (function buildVaultModel() {
    const group = models.vault;

    // 1. Faceted Metallic Core Safe
    const coreGeo = new THREE.DodecahedronGeometry(1.2, 0);
    const coreMat = new THREE.MeshStandardMaterial({
      color: 0x071124,
      metalness: 0.9,
      roughness: 0.15,
      wireframe: false
    });
    const coreMesh = new THREE.Mesh(coreGeo, coreMat);
    group.add(coreMesh);

    // 2. Outer Wireframe Polyhedron Shell
    const wireGeo = new THREE.IcosahedronGeometry(1.55, 1);
    const wireMat = new THREE.MeshBasicMaterial({
      color: 0x00f0ff,
      wireframe: true,
      transparent: true,
      opacity: 0.4
    });
    const wireMesh = new THREE.Mesh(wireGeo, wireMat);
    group.add(wireMesh);

    // 3. Gyroscopic Ring 1 (Horizontal / Tilted)
    const ringGeo1 = new THREE.TorusGeometry(2.1, 0.03, 16, 90);
    const ringMat1 = new THREE.MeshStandardMaterial({
      color: 0x00f0ff,
      emissive: 0x0088cc,
      emissiveIntensity: 0.6,
      metalness: 0.8,
      roughness: 0.2
    });
    const ring1 = new THREE.Mesh(ringGeo1, ringMat1);
    ring1.rotation.x = Math.PI / 3.2;
    group.add(ring1);

    // 4. Gyroscopic Ring 2 (Vertical / Orthogonal)
    const ringGeo2 = new THREE.TorusGeometry(2.35, 0.025, 16, 90);
    const ringMat2 = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      emissive: 0x6d28d9,
      emissiveIntensity: 0.5,
      metalness: 0.8,
      roughness: 0.2
    });
    const ring2 = new THREE.Mesh(ringGeo2, ringMat2);
    ring2.rotation.y = Math.PI / 2.8;
    group.add(ring2);

    // 5. Pulsing Biometric Node in Center
    const nodeGeo = new THREE.SphereGeometry(0.4, 32, 32);
    const nodeMat = new THREE.MeshStandardMaterial({
      color: 0x00f0ff,
      emissive: 0x00f0ff,
      emissiveIntensity: 0.8,
      roughness: 0.1
    });
    const centerNode = new THREE.Mesh(nodeGeo, nodeMat);
    group.add(centerNode);

    // 6. Orbiting Crystal Data Shards (4 Octahedra)
    const shardGroup = new THREE.Group();
    const shardGeo = new THREE.OctahedronGeometry(0.2, 0);
    const shardMat = new THREE.MeshStandardMaterial({
      color: 0x10b981,
      emissive: 0x059669,
      emissiveIntensity: 0.5,
      metalness: 0.9,
      roughness: 0.1
    });

    const shards = [];
    for (let i = 0; i < 4; i++) {
      const shard = new THREE.Mesh(shardGeo, shardMat);
      const angle = (i / 4) * Math.PI * 2;
      shard.position.set(Math.cos(angle) * 1.8, Math.sin(angle) * 0.4, Math.sin(angle) * 1.8);
      shardGroup.add(shard);
      shards.push(shard);
    }
    group.add(shardGroup);

    group.userData = { wireMesh, ring1, ring2, centerNode, shardGroup, shards };
    group.scale.set(1, 1, 1);
    scene.add(group);
  })();

  // ==========================================
  // MODEL 2: QUANTUM MASTER KEY & RING LOCK
  // ==========================================
  (function buildKeyModel() {
    const group = models.key;

    // 1. Cylindrical Key Shaft
    const shaftGeo = new THREE.CylinderGeometry(0.12, 0.12, 2.6, 16);
    const keyMat = new THREE.MeshStandardMaterial({
      color: 0x00f0ff,
      metalness: 0.85,
      roughness: 0.15,
      emissive: 0x006699,
      emissiveIntensity: 0.4
    });
    const shaft = new THREE.Mesh(shaftGeo, keyMat);
    shaft.position.y = -0.2;
    group.add(shaft);

    // 2. Key Bow (Handle Ring)
    const bowGeo = new THREE.TorusGeometry(0.65, 0.14, 16, 40);
    const bow = new THREE.Mesh(bowGeo, keyMat);
    bow.position.y = 1.35;
    group.add(bow);

    // 3. Central Holographic Crystal in Bow
    const crystalGeo = new THREE.OctahedronGeometry(0.35, 0);
    const crystalMat = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      emissive: 0x7c3aed,
      emissiveIntensity: 0.9,
      metalness: 0.9,
      roughness: 0.1
    });
    const crystal = new THREE.Mesh(crystalGeo, crystalMat);
    crystal.position.y = 1.35;
    group.add(crystal);

    // 4. Cyber Key Teeth (Bits)
    const toothMat = new THREE.MeshStandardMaterial({
      color: 0x10b981,
      metalness: 0.8,
      roughness: 0.2,
      emissive: 0x059669,
      emissiveIntensity: 0.6
    });

    const teeth = [
      { y: -0.9, w: 0.45, h: 0.18, d: 0.14 },
      { y: -1.2, w: 0.35, h: 0.15, d: 0.14 },
      { y: -1.45, w: 0.5, h: 0.16, d: 0.14 }
    ];

    teeth.forEach(t => {
      const toothGeo = new THREE.BoxGeometry(t.w, t.h, t.d);
      const toothMesh = new THREE.Mesh(toothGeo, toothMat);
      toothMesh.position.set(t.w / 2 + 0.05, t.y, 0);
      group.add(toothMesh);
    });

    // 5. Outer Concentric Quantum Lock Ring
    const lockRingGeo = new THREE.TorusGeometry(2.3, 0.05, 16, 80);
    const lockRingMat = new THREE.MeshBasicMaterial({
      color: 0x00f0ff,
      wireframe: true,
      transparent: true,
      opacity: 0.6
    });
    const lockRing = new THREE.Mesh(lockRingGeo, lockRingMat);
    group.add(lockRing);

    // 6. Floating Hash Orbit Ring
    const hashRingGeo = new THREE.TorusGeometry(1.8, 0.02, 16, 60);
    const hashRingMat = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      emissive: 0x8b5cf6,
      emissiveIntensity: 0.4
    });
    const hashRing = new THREE.Mesh(hashRingGeo, hashRingMat);
    hashRing.rotation.x = Math.PI / 2.5;
    group.add(hashRing);

    group.userData = { crystal, lockRing, hashRing };
    group.scale.set(0.001, 0.001, 0.001);
    scene.add(group);
  })();

  // ==========================================
  // MODEL 3: CYBER DEFENSE SHIELD & MATRIX
  // ==========================================
  (function buildShieldModel() {
    const group = models.shield;

    // 1. Faceted Shield Face (Hexagonal Extruded Prism)
    const shieldShape = new THREE.Shape();
    const s = 1.35;
    shieldShape.moveTo(0, s * 1.3);
    shieldShape.lineTo(s * 1.05, s * 0.7);
    shieldShape.lineTo(s * 0.9, -s * 0.4);
    shieldShape.lineTo(0, -s * 1.3);
    shieldShape.lineTo(-s * 0.9, -s * 0.4);
    shieldShape.lineTo(-s * 1.05, s * 0.7);
    shieldShape.closePath();

    const extrudeSettings = { depth: 0.25, bevelEnabled: true, bevelSegments: 3, steps: 1, bevelSize: 0.1, bevelThickness: 0.1 };
    const shieldGeo = new THREE.ExtrudeGeometry(shieldShape, extrudeSettings);
    shieldGeo.center();

    const shieldMat = new THREE.MeshStandardMaterial({
      color: 0x0b172d,
      metalness: 0.9,
      roughness: 0.15
    });
    const shieldMesh = new THREE.Mesh(shieldGeo, shieldMat);
    group.add(shieldMesh);

    // 2. Glowing Shield Holographic Wireframe
    const wireMat = new THREE.MeshBasicMaterial({
      color: 0x00f0ff,
      wireframe: true,
      transparent: true,
      opacity: 0.45
    });
    const wireShield = new THREE.Mesh(shieldGeo, wireMat);
    wireShield.scale.set(1.05, 1.05, 1.05);
    group.add(wireShield);

    // 3. Center Cyber Emblem / Node
    const emblemGeo = new THREE.CylinderGeometry(0.35, 0.35, 0.35, 6);
    const emblemMat = new THREE.MeshStandardMaterial({
      color: 0x10b981,
      emissive: 0x10b981,
      emissiveIntensity: 0.8,
      metalness: 0.9
    });
    const emblem = new THREE.Mesh(emblemGeo, emblemMat);
    emblem.rotation.x = Math.PI / 2;
    emblem.position.z = 0.2;
    group.add(emblem);

    // 4. Orbiting Guard Satellites
    const satGroup = new THREE.Group();
    const satGeo = new THREE.DodecahedronGeometry(0.18, 0);
    const satMat = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      emissive: 0x7c3aed,
      emissiveIntensity: 0.7
    });

    const sats = [];
    for (let i = 0; i < 3; i++) {
      const sat = new THREE.Mesh(satGeo, satMat);
      const angle = (i / 3) * Math.PI * 2;
      sat.position.set(Math.cos(angle) * 2.2, Math.sin(angle) * 2.0, 0);
      satGroup.add(sat);
      sats.push(sat);
    }
    group.add(satGroup);

    // 5. Outer Barrier Force Ring
    const barrierGeo = new THREE.TorusGeometry(2.4, 0.02, 16, 70);
    const barrierMat = new THREE.MeshBasicMaterial({
      color: 0x00f0ff,
      transparent: true,
      opacity: 0.3
    });
    const barrier = new THREE.Mesh(barrierGeo, barrierMat);
    group.add(barrier);

    group.userData = { shieldMesh, wireShield, emblem, satGroup, sats, barrier };
    group.scale.set(0.001, 0.001, 0.001);
    scene.add(group);
  })();

  // ==========================================
  // AMBIENT CYBER DATA PARTICLE CLOUD
  // ==========================================
  const particleCount = 140;
  const particleGeo = new THREE.BufferGeometry();
  const particlePos = new Float32Array(particleCount * 3);
  for (let i = 0; i < particleCount * 3; i += 3) {
    particlePos[i] = (Math.random() - 0.5) * 11;
    particlePos[i + 1] = (Math.random() - 0.5) * 11;
    particlePos[i + 2] = (Math.random() - 0.5) * 8;
  }
  particleGeo.setAttribute('position', new THREE.BufferAttribute(particlePos, 3));
  const particleMat = new THREE.PointsMaterial({
    color: 0x00f0ff,
    size: 0.035,
    transparent: true,
    opacity: 0.55
  });
  const particles = new THREE.Points(particleGeo, particleMat);
  scene.add(particles);

  // ==========================================
  // INTERACTION & ANIMATION ENGINE
  // ==========================================
  const mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };

  window.addEventListener('mousemove', (e) => {
    const rect = container.getBoundingClientRect();
    const cx = rect.left + rect.width / 2;
    const cy = rect.top + rect.height / 2;
    mouse.targetX = ((e.clientX - cx) / (window.innerWidth / 2)) * 1.2;
    mouse.targetY = -((e.clientY - cy) / (window.innerHeight / 2)) * 1.2;
  });

  // Shockwave burst on click
  container.addEventListener('click', () => {
    if (window.VaultAudioInstance) window.VaultAudioInstance.click();
    typingBoost = 2.0;

    const group = models[activeModelKey];
    if (!group) return;
    let s = 1.0;
    const expand = () => {
      s += 0.04;
      group.scale.set(s, s, s);
      if (s < 1.25) {
        requestAnimationFrame(expand);
      } else {
        const shrink = () => {
          s -= 0.025;
          group.scale.set(s, s, s);
          if (s > 1.0) requestAnimationFrame(shrink);
          else group.scale.set(1, 1, 1);
        };
        requestAnimationFrame(shrink);
      }
    };
    expand();
  });

  // Typing responsiveness: accelerate 3D rotations when user enters credentials
  const emailInput = document.getElementById('loginEmail');
  const pwdInput = document.getElementById('loginPassword');
  [emailInput, pwdInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', () => {
      typingBoost = Math.min(2.5, typingBoost + 0.5);
      keyLight.intensity = 5.0;
      fillLight.intensity = 3.5;
    });
  });

  // Window resize handler
  function onResize() {
    const w = container.clientWidth;
    const h = container.clientHeight;
    if (w === 0 || h === 0) return;
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    renderer.setSize(w, h);
  }
  window.addEventListener('resize', onResize);

  // Switch Model Function
  window.switchLogin3DModel = function(modelKey) {
    if (!models[modelKey]) return;
    activeModelKey = modelKey;

    Object.keys(targetScales).forEach(k => {
      targetScales[k] = (k === modelKey) ? 1.0 : 0.001;
    });

    // Update active button state in UI
    const btns = document.querySelectorAll('.model-switch-btn');
    btns.forEach(b => {
      if (b.getAttribute('data-model') === modelKey) {
        b.classList.add('active');
      } else {
        b.classList.remove('active');
      }
    });

    // Update telemetry status text
    const statusText = document.getElementById('model-status-text');
    if (statusText) {
      if (modelKey === 'vault') statusText.innerText = 'VAULT CORE ARMED';
      else if (modelKey === 'key') statusText.innerText = 'QUANTUM KEY SYNCED';
      else if (modelKey === 'shield') statusText.innerText = 'ACTIVE DEFENSE ON';
    }

    if (window.VaultAudioInstance) window.VaultAudioInstance.click();
  };

  // Main Animation Loop
  let clock = 0;
  function animate() {
    requestAnimationFrame(animate);
    clock += 0.015;

    // Smooth typing boost decay
    if (typingBoost > 0) {
      typingBoost *= 0.96;
      if (typingBoost < 0.01) typingBoost = 0;
    }
    const currentSpeed = 1.0 + typingBoost;

    // Mouse interpolation
    mouse.x += (mouse.targetX - mouse.x) * 0.05;
    mouse.y += (mouse.targetY - mouse.y) * 0.05;

    // Smooth model morphing transition
    Object.keys(models).forEach(k => {
      currentScales[k] += (targetScales[k] - currentScales[k]) * 0.1;
      const s = Math.max(0.001, currentScales[k]);
      models[k].scale.set(s, s, s);
      models[k].visible = currentScales[k] > 0.01;
    });

    // 1. Animate Vault Model
    if (models.vault.visible) {
      const v = models.vault;
      v.rotation.y += 0.007 * currentSpeed;
      v.rotation.x = mouse.y * 0.35;
      v.rotation.z = mouse.x * 0.2;

      if (v.userData.ring1) v.userData.ring1.rotation.z += 0.012 * currentSpeed;
      if (v.userData.ring2) v.userData.ring2.rotation.x += 0.014 * currentSpeed;

      const pScale = 1 + Math.sin(clock * 3) * 0.06;
      if (v.userData.centerNode) v.userData.centerNode.scale.set(pScale, pScale, pScale);

      if (v.userData.shardGroup) {
        v.userData.shardGroup.rotation.y -= 0.018 * currentSpeed;
        v.userData.shards.forEach((s, idx) => {
          s.rotation.x += 0.02;
          s.rotation.y += 0.02;
        });
      }
    }

    // 2. Animate Key Model
    if (models.key.visible) {
      const k = models.key;
      k.rotation.y += 0.012 * currentSpeed;
      k.rotation.x = mouse.y * 0.4;
      k.rotation.z = Math.sin(clock) * 0.08 + mouse.x * 0.2;

      if (k.userData.crystal) {
        k.userData.crystal.rotation.y += 0.03 * currentSpeed;
        const cPulse = 1 + Math.sin(clock * 4) * 0.1;
        k.userData.crystal.scale.set(cPulse, cPulse, cPulse);
      }
      if (k.userData.lockRing) k.userData.lockRing.rotation.z -= 0.008 * currentSpeed;
      if (k.userData.hashRing) k.userData.hashRing.rotation.x += 0.015 * currentSpeed;
    }

    // 3. Animate Shield Model
    if (models.shield.visible) {
      const sh = models.shield;
      sh.rotation.y = Math.sin(clock * 1.5) * 0.35 + mouse.x * 0.3;
      sh.rotation.x = mouse.y * 0.3;

      if (sh.userData.emblem) {
        sh.userData.emblem.rotation.z += 0.015 * currentSpeed;
      }
      if (sh.userData.satGroup) {
        sh.userData.satGroup.rotation.z += 0.01 * currentSpeed;
        sh.userData.sats.forEach(sat => {
          sat.rotation.x += 0.02;
          sat.rotation.y += 0.02;
        });
      }
      if (sh.userData.barrier) {
        sh.userData.barrier.rotation.x += 0.006 * currentSpeed;
      }
    }

    // Particles motion
    if (particles) {
      particles.rotation.y += 0.001;
      particles.rotation.x = mouse.y * 0.05;
    }

    // Lights breathing
    keyLight.intensity = 3.5 + Math.sin(clock * 2) * 0.5 + typingBoost;
    fillLight.intensity = 2.5 + Math.cos(clock * 2) * 0.4 + typingBoost;

    renderer.render(scene, camera);
  }

  animate();
})();
