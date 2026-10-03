/**
 * 3D Holographic Cyber Vault Engine (Three.js + Web Audio API)
 */
class CyberVaultScene {
  constructor(canvasId, status = 'ACTIVE') {
    this.container = document.getElementById(canvasId);
    if (!this.container || typeof THREE === 'undefined') return;

    this.status = status;
    this.mouse = { x: 0, y: 0, targetX: 0, targetY: 0 };
    this.init();
    this.createVaultMesh();
    this.createParticles();
    this.setupEvents();
    this.animate();
  }

  init() {
    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(45, this.container.clientWidth / this.container.clientHeight, 0.1, 1000);
    this.camera.position.z = 7;

    this.renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
    this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    this.container.appendChild(this.renderer.domElement);

    // Dynamic Lights
    const ambient = new THREE.AmbientLight(0x0f172a, 1.5);
    this.scene.add(ambient);

    this.pointLight1 = new THREE.PointLight(this.getColorHex(), 4, 20);
    this.pointLight1.position.set(4, 5, 5);
    this.scene.add(this.pointLight1);

    this.pointLight2 = new THREE.PointLight(0x6366f1, 2, 20);
    this.pointLight2.position.set(-4, -4, -3);
    this.scene.add(this.pointLight2);
  }

  getColorHex() {
    if (this.status === 'TRIGGERED') return 0xef4444; // Crimson
    if (this.status === 'GRACE_PERIOD') return 0xf59e0b; // Amber
    return 0x00f2fe; // Cyan / Electric Blue
  }

  createVaultMesh() {
    this.vaultGroup = new THREE.Group();

    // 1. Core Glowing Polyhedron
    const coreGeo = new THREE.IcosahedronGeometry(1.2, 0);
    const coreMat = new THREE.MeshStandardMaterial({
      color: 0x0a192f,
      roughness: 0.1,
      metalness: 0.9,
      wireframe: false,
    });
    this.coreMesh = new THREE.Mesh(coreGeo, coreMat);
    this.vaultGroup.add(this.coreMesh);

    // 2. Holographic Outer Wireframe Shell
    const wireGeo = new THREE.IcosahedronGeometry(1.4, 1);
    const wireMat = new THREE.MeshBasicMaterial({
      color: this.getColorHex(),
      wireframe: true,
      transparent: true,
      opacity: 0.45
    });
    this.wireMesh = new THREE.Mesh(wireGeo, wireMat);
    this.vaultGroup.add(this.wireMesh);

    // 3. Floating Orbital Rings
    const ringGeo1 = new THREE.TorusGeometry(2.0, 0.02, 16, 100);
    const ringMat1 = new THREE.MeshStandardMaterial({
      color: this.getColorHex(),
      metalness: 0.8,
      roughness: 0.2,
      emissive: this.getColorHex(),
      emissiveIntensity: 0.4
    });
    this.ring1 = new THREE.Mesh(ringGeo1, ringMat1);
    this.ring1.rotation.x = Math.PI / 3;
    this.vaultGroup.add(this.ring1);

    const ringGeo2 = new THREE.TorusGeometry(2.3, 0.015, 16, 100);
    const ringMat2 = new THREE.MeshStandardMaterial({
      color: 0x8b5cf6,
      metalness: 0.8,
      roughness: 0.2,
      emissive: 0x6d28d9,
      emissiveIntensity: 0.3
    });
    this.ring2 = new THREE.Mesh(ringGeo2, ringMat2);
    this.ring2.rotation.y = Math.PI / 4;
    this.vaultGroup.add(this.ring2);

    // 4. Biometric Center Node
    const centerGeo = new THREE.SphereGeometry(0.35, 32, 32);
    const centerMat = new THREE.MeshBasicMaterial({
      color: this.getColorHex()
    });
    this.centerNode = new THREE.Mesh(centerGeo, centerMat);
    this.vaultGroup.add(this.centerNode);

    this.scene.add(this.vaultGroup);
  }

  createParticles() {
    const particleCount = 200;
    const geometry = new THREE.BufferGeometry();
    const positions = new Float32Array(particleCount * 3);

    for (let i = 0; i < particleCount * 3; i += 3) {
      positions[i] = (Math.random() - 0.5) * 14;
      positions[i + 1] = (Math.random() - 0.5) * 14;
      positions[i + 2] = (Math.random() - 0.5) * 10;
    }

    geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
    const material = new THREE.PointsMaterial({
      color: this.getColorHex(),
      size: 0.04,
      transparent: true,
      opacity: 0.6
    });

    this.particles = new THREE.Points(geometry, material);
    this.scene.add(this.particles);
  }

  setupEvents() {
    window.addEventListener('resize', () => {
      if (!this.container) return;
      this.camera.aspect = this.container.clientWidth / this.container.clientHeight;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
    });

    window.addEventListener('mousemove', (e) => {
      this.mouse.targetX = (e.clientX / window.innerWidth) * 2 - 1;
      this.mouse.targetY = -(e.clientY / window.innerHeight) * 2 + 1;
    });
  }

  pulseBurst() {
    if (!this.vaultGroup) return;
    let scale = 1.0;
    const anim = () => {
      scale += 0.05;
      this.vaultGroup.scale.set(scale, scale, scale);
      if (scale < 1.35) {
        requestAnimationFrame(anim);
      } else {
        const shrink = () => {
          scale -= 0.03;
          this.vaultGroup.scale.set(scale, scale, scale);
          if (scale > 1.0) requestAnimationFrame(shrink);
          else this.vaultGroup.scale.set(1, 1, 1);
        };
        requestAnimationFrame(shrink);
      }
    };
    anim();
  }

  animate() {
    requestAnimationFrame(() => this.animate());

    // Mouse interpolation
    this.mouse.x += (this.mouse.targetX - this.mouse.x) * 0.05;
    this.mouse.y += (this.mouse.targetY - this.mouse.y) * 0.05;

    if (this.vaultGroup) {
      this.vaultGroup.rotation.y += 0.008;
      this.vaultGroup.rotation.x = this.mouse.y * 0.4;
      this.vaultGroup.rotation.z = this.mouse.x * 0.2;

      this.ring1.rotation.z += 0.012;
      this.ring2.rotation.x += 0.015;

      const time = Date.now() * 0.002;
      const pulse = 1 + Math.sin(time) * 0.05;
      this.centerNode.scale.set(pulse, pulse, pulse);
    }

    if (this.particles) {
      this.particles.rotation.y += 0.001;
    }

    this.renderer.render(this.scene, this.camera);
  }
}

/**
 * Procedural Web Audio Sound Synthesizer
 */
class VaultAudio {
  constructor() {
    this.enabled = localStorage.getItem('vault_sound') !== 'muted';
    this.ctx = null;
  }

  getAudioContext() {
    if (!this.ctx && typeof AudioContext !== 'undefined') {
      this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return this.ctx;
  }

  toggle() {
    this.enabled = !this.enabled;
    localStorage.setItem('vault_sound', this.enabled ? 'active' : 'muted');
    return this.enabled;
  }

  playTone(freq, type = 'sine', duration = 0.15, gainVal = 0.08) {
    if (!this.enabled) return;
    try {
      const ctx = this.getAudioContext();
      if (!ctx) return;
      if (ctx.state === 'suspended') ctx.resume();

      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = type;
      osc.frequency.setValueAtTime(freq, ctx.currentTime);
      gain.gain.setValueAtTime(gainVal, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start();
      osc.stop(ctx.currentTime + duration);
    } catch(e) {}
  }

  click() { this.playTone(800, 'sine', 0.06, 0.04); }
  unlock() { 
    this.playTone(523.25, 'sine', 0.1, 0.06); 
    setTimeout(() => this.playTone(659.25, 'sine', 0.1, 0.06), 80);
    setTimeout(() => this.playTone(1046.50, 'sine', 0.2, 0.08), 160);
  }
  pulse() {
    this.playTone(440, 'triangle', 0.15, 0.08);
    setTimeout(() => this.playTone(880, 'sine', 0.2, 0.06), 120);
  }
  alert() {
    this.playTone(220, 'sawtooth', 0.3, 0.07);
  }
}

window.VaultAudioInstance = new VaultAudio();

/**
 * 3D Tilt Card Engine
 */
function init3DTiltCards() {
  const cards = document.querySelectorAll('.tilt-3d');
  cards.forEach(card => {
    card.addEventListener('mousemove', e => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      const centerX = rect.width / 2;
      const centerY = rect.height / 2;
      const rotateX = ((y - centerY) / centerY) * -8;
      const rotateY = ((x - centerX) / centerX) * 8;

      card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.01, 1.01, 1.01)`;
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  init3DTiltCards();
});
