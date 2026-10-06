/**
 * Digital Legacy Vault - Client-Side Zero-Knowledge Cryptography Engine
 * Native Web Crypto API (window.crypto.subtle)
 * 
 * Cryptographic Specifications:
 * - Key Derivation: PBKDF2 (RFC 2898 / RFC 8018)
 * - Hash: SHA-256
 * - Iterations: 100,000
 * - Cipher: AES-256-GCM (NIST SP 800-38D)
 * - IV: 96-bit (12 bytes) cryptographically secure random values
 * - Tag: 128-bit (16 bytes) authentication tag
 * - File Container: ZKV1 magic header + 12-byte IV + AES-GCM ciphertext
 */

(function (root, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.DLVCrypto = factory();
  }
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const PBKDF2_ITERATIONS = 100000;
  const ZK_FILE_MAGIC = new Uint8Array([0x5A, 0x4B, 0x56, 0x31]); // "ZKV1"

  /**
   * Safe base64 encoding for Uint8Array (handles large buffers)
   */
  function uint8ToBase64(u8) {
    let binary = '';
    const len = u8.byteLength;
    const chunkSize = 8192;
    for (let i = 0; i < len; i += chunkSize) {
      binary += String.fromCharCode.apply(null, u8.subarray(i, Math.min(i + chunkSize, len)));
    }
    return btoa(binary);
  }

  /**
   * Safe base64 decoding to Uint8Array
   */
  function base64ToUint8(b64) {
    const binary = atob(b64);
    const len = binary.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
  }

  /**
   * Converts ArrayBuffer to Hex String
   */
  function bufferToHex(buffer) {
    const bytes = new Uint8Array(buffer);
    return Array.from(bytes)
      .map(b => b.toString(16).padStart(2, '0'))
      .join('');
  }

  /**
   * Converts Hex String to Uint8Array
   */
  function hexToUint8(hex) {
    const bytes = new Uint8Array(hex.length / 2);
    for (let i = 0; i < bytes.length; i++) {
      bytes[i] = parseInt(hex.substr(i * 2, 2), 16);
    }
    return bytes;
  }

  /**
   * Derives a deterministic salt from user email
   */
  async function deriveEmailSalt(email) {
    const normalized = email.toLowerCase().trim() + ':dlv_zk_salt_v1';
    const encoded = new TextEncoder().encode(normalized);
    const hash = await window.crypto.subtle.digest('SHA-256', encoded);
    return new Uint8Array(hash);
  }

  /**
   * Derives a 256-bit AES-GCM CryptoKey directly from the master password and user email
   */
  async function deriveKeyFromPassword(password, email, customSalt = null) {
    if (!window.crypto || !window.crypto.subtle) {
      throw new Error('Web Crypto API is not supported in this environment.');
    }

    const salt = customSalt || (await deriveEmailSalt(email));

    // Import password as PBKDF2 base key material
    const keyMaterial = await window.crypto.subtle.importKey(
      'raw',
      new TextEncoder().encode(password),
      { name: 'PBKDF2' },
      false,
      ['deriveKey']
    );

    // Derive 256-bit AES-GCM key
    return await window.crypto.subtle.deriveKey(
      {
        name: 'PBKDF2',
        salt: salt,
        iterations: PBKDF2_ITERATIONS,
        hash: 'SHA-256'
      },
      keyMaterial,
      { name: 'AES-GCM', length: 256 },
      true, // extractable for session storage caching
      ['encrypt', 'decrypt']
    );
  }

  /**
   * Exports raw AES-GCM key to hex string
   */
  async function exportKeyToHex(cryptoKey) {
    const raw = await window.crypto.subtle.exportKey('raw', cryptoKey);
    return bufferToHex(raw);
  }

  /**
   * Imports raw hex string as AES-GCM CryptoKey
   */
  async function importKeyFromHex(hex) {
    const bytes = hexToUint8(hex);
    return await window.crypto.subtle.importKey(
      'raw',
      bytes,
      { name: 'AES-GCM' },
      true,
      ['encrypt', 'decrypt']
    );
  }

  /**
   * Stores key securely in sessionStorage for the active tab session
   */
  async function saveKeyToSession(email, cryptoKey) {
    try {
      const hex = await exportKeyToHex(cryptoKey);
      const storageKey = 'dlv_zk_key_' + email.toLowerCase().trim();
      sessionStorage.setItem(storageKey, hex);
      sessionStorage.setItem('dlv_active_email', email.toLowerCase().trim());
      return true;
    } catch (e) {
      console.warn('SessionStorage key save error:', e);
      return false;
    }
  }

  /**
   * Retrieves active key from sessionStorage
   */
  async function loadKeyFromSession(email) {
    try {
      const storageKey = 'dlv_zk_key_' + email.toLowerCase().trim();
      const hex = sessionStorage.getItem(storageKey);
      if (!hex) return null;
      return await importKeyFromHex(hex);
    } catch (e) {
      console.warn('SessionStorage key load error:', e);
      return null;
    }
  }

  /**
   * Clears key from sessionStorage on logout
   */
  function clearKeySession(email) {
    try {
      if (email) {
        sessionStorage.removeItem('dlv_zk_key_' + email.toLowerCase().trim());
      }
      sessionStorage.removeItem('dlv_active_email');
    } catch (e) {}
  }

  /**
   * Encrypts plaintext string using AES-256-GCM.
   * Splits Web Crypto output into standard ciphertext and 16-byte tag.
   */
  async function encryptText(plaintext, cryptoKey) {
    const iv = window.crypto.getRandomValues(new Uint8Array(12)); // 96-bit IV
    const encoded = new TextEncoder().encode(plaintext);

    const encryptedBuffer = await window.crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: iv, tagLength: 128 },
      cryptoKey,
      encoded
    );

    const fullArray = new Uint8Array(encryptedBuffer);
    const tagArray = fullArray.slice(-16);
    const cipherArray = fullArray.slice(0, -16);

    return {
      ciphertext: uint8ToBase64(cipherArray),
      iv: uint8ToBase64(iv),
      tag: uint8ToBase64(tagArray)
    };
  }

  /**
   * Decrypts ciphertext string using AES-256-GCM.
   */
  async function decryptText(ciphertextB64, ivB64, tagB64, cryptoKey) {
    const cipherBytes = base64ToUint8(ciphertextB64);
    const ivBytes = base64ToUint8(ivB64);
    const tagBytes = base64ToUint8(tagB64);

    // Combine ciphertext and tag for Web Crypto API
    const combined = new Uint8Array(cipherBytes.byteLength + tagBytes.byteLength);
    combined.set(cipherBytes, 0);
    combined.set(tagBytes, cipherBytes.byteLength);

    const decryptedBuffer = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: ivBytes, tagLength: 128 },
      cryptoKey,
      combined
    );

    return new TextDecoder('utf-8').decode(decryptedBuffer);
  }

  /**
   * Encrypts a File or Blob with AES-256-GCM locally in the browser
   */
  async function encryptFile(file, cryptoKey) {
    const fileBuffer = await file.arrayBuffer();
    const iv = window.crypto.getRandomValues(new Uint8Array(12));

    const encryptedBuffer = await window.crypto.subtle.encrypt(
      { name: 'AES-GCM', iv: iv, tagLength: 128 },
      cryptoKey,
      fileBuffer
    );

    // Format: [4-byte magic 'ZKV1'] + [12-byte IV] + [encrypted buffer]
    const encryptedArray = new Uint8Array(encryptedBuffer);
    const totalLength = 4 + 12 + encryptedArray.byteLength;
    const container = new Uint8Array(totalLength);

    container.set(ZK_FILE_MAGIC, 0);
    container.set(iv, 4);
    container.set(encryptedArray, 16);

    return new File([container], file.name, {
      type: 'application/octet-stream',
      lastModified: Date.now()
    });
  }

  /**
   * Decrypts an encrypted ArrayBuffer locally in the browser
   */
  async function decryptFile(encryptedBuffer, cryptoKey) {
    const bytes = new Uint8Array(encryptedBuffer);

    if (bytes.byteLength < 32) {
      throw new Error('Encrypted file buffer is too small to be valid.');
    }

    // Verify magic header
    const hasMagic =
      bytes[0] === ZK_FILE_MAGIC[0] &&
      bytes[1] === ZK_FILE_MAGIC[1] &&
      bytes[2] === ZK_FILE_MAGIC[2] &&
      bytes[3] === ZK_FILE_MAGIC[3];

    let iv;
    let payload;

    if (hasMagic) {
      iv = bytes.slice(4, 16);
      payload = bytes.slice(16);
    } else {
      // Fallback for headerless container (first 12 bytes IV)
      iv = bytes.slice(0, 12);
      payload = bytes.slice(12);
    }

    const decryptedBuffer = await window.crypto.subtle.decrypt(
      { name: 'AES-GCM', iv: iv, tagLength: 128 },
      cryptoKey,
      payload
    );

    return new Blob([decryptedBuffer]);
  }

  /**
   * Checks if Web Crypto API is available in the current browser
   */
  function isSupported() {
    return !!(window.crypto && window.crypto.subtle);
  }

  return {
    deriveEmailSalt,
    deriveKeyFromPassword,
    exportKeyToHex,
    importKeyFromHex,
    saveKeyToSession,
    loadKeyFromSession,
    clearKeySession,
    encryptText,
    decryptText,
    encryptFile,
    decryptFile,
    uint8ToBase64,
    base64ToUint8,
    isSupported
  };
});
