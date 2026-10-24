(() => {
  const root = document.querySelector('[data-staff-qr-scanner]');
  if (!root) return;

  const endpoint = root.dataset.endpoint || 'staff-qr-lookup.php';
  const video = document.getElementById('staffQrVideo');
  const html5Reader = document.getElementById('staffQrHtml5Reader');
  const status = document.getElementById('staffQrCameraStatus');
  const startBtn = document.getElementById('startStaffQrScanner');
  const stopBtn = document.getElementById('stopStaffQrScanner');
  const photoInput = document.getElementById('staffQrPhotoInput');
  const form = document.getElementById('staffQrManualForm');
  const input = document.getElementById('staffQrManualInput');
  const scanLogBody = document.getElementById('staffQrScanLogBody');
  const idModal = document.getElementById('staffQrIdModal');
  const idPreview = document.getElementById('staffQrIdPreview');

  let stream = null;
  let detector = null;
  let scanTimer = null;
  let scanning = false;
  let checking = false;
  let fileScanInProgress = false;
  let cameraRequestVersion = 0;
  let scannerStarting = false;

  const escapeHtml = (value) => String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');

  const setStatus = (message, tone = '') => {
    if (!status) return;
    status.textContent = message;
    status.dataset.tone = tone;
  };

  const setScannerState = (isScanning) => {
    scanning = isScanning;
    if (startBtn) startBtn.disabled = isScanning;
    if (stopBtn) stopBtn.disabled = !isScanning;
  };

  let qrReaderLoading = null;
  const loadJsQr = () => {
    if (window.jsQR) return Promise.resolve();
    if (!qrReaderLoading) qrReaderLoading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'js/vendor/jsQR.js';
      script.async = true;
      script.onload = () => { if (window.jsQR) resolve(); else { qrReaderLoading = null; reject(new Error('QR reader could not be loaded. Refresh and try again.')); } };
      script.onerror = () => { qrReaderLoading = null; script.remove(); reject(new Error('QR reader could not be loaded. Refresh and try again.')); };
      document.head.appendChild(script);
    });
    return qrReaderLoading;
  };

  const stopScanner = async (message = 'Scanner stopped.') => {
    cameraRequestVersion++;
    setScannerState(false);
    if (scanTimer) {
      clearTimeout(scanTimer);
      scanTimer = null;
    }
    if (stream) {
      stream.getTracks().forEach((track) => track.stop());
      stream = null;
    }
    if (video) {
      video.srcObject = null;
      video.hidden = false;
    }
    if (html5Reader) {
      html5Reader.hidden = true;
    }
    setStatus(message);
  };

  const makeBadgeClass = (value) => {
    if (value === 'Verified' || value === 'Active') return 'badge-active';
    if (value === 'Not Found' || value === 'Rejected') return 'badge-rejected';
    return 'badge-open';
  };

  const prependScanLog = (scanLog) => {
    if (!scanLogBody || !scanLog) return;

    const emptyRow = scanLogBody.querySelector('td[colspan]');
    if (emptyRow) {
      emptyRow.closest('tr')?.remove();
    }

    const row = document.createElement('tr');
    const dateCell = document.createElement('td');
    const residentCell = document.createElement('td');
    const resultCell = document.createElement('td');
    const staffCell = document.createElement('td');
    dateCell.dataset.label = 'Date and time';
    residentCell.dataset.label = 'Resident';
    resultCell.dataset.label = 'Result';
    staffCell.dataset.label = 'Scanner';
    const badge = document.createElement('span');

    dateCell.textContent = scanLog.date || '';
    residentCell.textContent = scanLog.resident || '';
    badge.className = `badge ${makeBadgeClass(scanLog.result)}`;
    badge.textContent = scanLog.result || '';
    resultCell.appendChild(badge);
    staffCell.textContent = scanLog.staff || '';

    row.append(dateCell, residentCell, resultCell, staffCell);
    scanLogBody.prepend(row);
  };

  const setText = (id, value) => {
    const element = document.getElementById(id);
    if (element) element.textContent = value || '-';
  };

  const badgeHtml = (value) => `<span class="badge ${makeBadgeClass(value)}">${escapeHtml(value || '-')}</span>`;

  const openIdModal = () => {
    if (!idModal) return;
    window.barangayOpenModal(idModal.id);
  };

  const renderResult = (data) => {
    const resident = data.resident;
    const result = data.result || 'Not Found';
    const scannedCode = data.scanned_code || '';
    const resultBadge = document.getElementById('staffQrIdResult');
    if (resultBadge) {
      resultBadge.className = `badge ${makeBadgeClass(result)}`;
      resultBadge.textContent = result;
    }
    if (idPreview) {
      idPreview.classList.remove('qr-id-preview--verified', 'qr-id-preview--warning', 'qr-id-preview--not-found');
      idPreview.classList.add(result === 'Verified' ? 'qr-id-preview--verified' : (resident ? 'qr-id-preview--warning' : 'qr-id-preview--not-found'));
    }

    if (!resident) {
      const photo = document.getElementById('staffQrIdPhoto');
      if (photo) photo.textContent = '?';
      setText('staffQrIdCode', scannedCode || 'Unknown QR');
      setText('staffQrIdName', 'Resident not found');
      setText('staffQrIdMobile', '-');
      setText('staffQrIdPurok', '-');
      setText('staffQrIdHousehold', '-');
      setText('staffQrIdBirthdate', '-');
      setText('staffQrIdGender', '-');
      setText('staffQrIdCivilStatus', '-');
      setText('staffQrIdAddress', '-');
      const account = document.getElementById('staffQrIdAccount');
      const verification = document.getElementById('staffQrIdVerification');
      if (account) account.innerHTML = badgeHtml(result);
      if (verification) verification.innerHTML = badgeHtml('Not Found');
      const qr = document.getElementById('staffQrIdQr');
      if (qr) {
        qr.innerHTML = `<div class="resident-id-card__qr-empty">QR</div><span>${escapeHtml(scannedCode || '-')}</span>`;
      }
      setText('staffQrIdMessage', data.message || 'No resident record found.');
      openIdModal();
      return;
    }

    const initial = (resident.name || '?').trim().charAt(0).toUpperCase() || '?';
    const photo = document.getElementById('staffQrIdPhoto');
    if (photo) {
      photo.innerHTML = resident.profile_photo_path
        ? `<img src="${escapeHtml(resident.profile_photo_path)}" alt="${escapeHtml(resident.name)}">`
        : escapeHtml(initial);
    }
    setText('staffQrIdCode', resident.id || '-');
    setText('staffQrIdName', resident.name || '-');
    setText('staffQrIdMobile', resident.mobile || '-');
    setText('staffQrIdPurok', resident.purok || '-');
    setText('staffQrIdHousehold', resident.household || '-');
    setText('staffQrIdBirthdate', `${resident.birthdate || '-'}${resident.age ? ` (${resident.age} yrs old)` : ''}`);
    setText('staffQrIdGender', resident.gender || '-');
    setText('staffQrIdCivilStatus', resident.civil_status || '-');
    setText('staffQrIdAddress', resident.address || '-');

    const account = document.getElementById('staffQrIdAccount');
    const verification = document.getElementById('staffQrIdVerification');
    if (account) account.innerHTML = badgeHtml(resident.status);
    if (verification) verification.innerHTML = badgeHtml(resident.verification_status);

    const qr = document.getElementById('staffQrIdQr');
    if (qr) {
      qr.innerHTML = resident.qr_image_url
        ? `<img src="${escapeHtml(resident.qr_image_url)}" alt="Resident QR code"><span>${escapeHtml(scannedCode || resident.id || '-')}</span>`
        : `<div class="resident-id-card__qr-empty">QR</div><span>${escapeHtml(scannedCode || resident.id || '-')}</span>`;
    }
    setText('staffQrIdMessage', data.message || 'Resident record loaded.');
    openIdModal();
  };

  const verifyCode = async (code) => {
    const qrCode = String(code || '').trim();
    if (!qrCode || checking) return;

    checking = true;
    setStatus('Checking resident record...', 'info');
    if (input) input.value = qrCode;

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({ qr_code: qrCode }),
        signal: AbortSignal.timeout(12000)
      });
      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Unable to verify this QR code.');
      }
      renderResult(data);
      prependScanLog(data.scan_log);
      setStatus(data.message || 'Scan complete.', data.result === 'Verified' ? 'success' : 'warning');
    } catch (error) {
      renderResult({
        result: 'Not Found',
        scanned_code: qrCode,
        message: error.message || 'Unable to verify this QR code.',
        resident: null
      });
      setStatus(error.message || 'Unable to verify this QR code.', 'error');
    } finally {
      checking = false;
    }
  };

  const scanLoop = async () => {
    if (!scanning || !video) return;
    const version = cameraRequestVersion;
    try {
      if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
        let rawValue = '';
        if (detector) {
          try { rawValue = (await detector.detect(video))[0]?.rawValue || ''; }
          catch { detector = null; }
        }
        if (!rawValue) {
          await loadJsQr();
          if (version !== cameraRequestVersion || !scanning) return;
          const canvas = document.createElement('canvas');
          const scale = Math.min(1,960 / Math.max(video.videoWidth,video.videoHeight));
          canvas.width = Math.max(1, Math.round(video.videoWidth * scale));
          canvas.height = Math.max(1, Math.round(video.videoHeight * scale));
          const context = canvas.getContext('2d', {willReadFrequently:true});
          if (context) { context.drawImage(video,0,0,canvas.width,canvas.height); rawValue = await decodeCanvasWithJsQr(canvas); }
        }
        if (version !== cameraRequestVersion || !scanning) return;
        if (rawValue) {
          await stopScanner('QR detected. Loading resident details...');
          await verifyCode(rawValue);
          return;
        }
      }
    } catch {
      setStatus('Hold the camera steady, or use a QR photo or resident ID.', 'warning');
    }
    if (version === cameraRequestVersion && scanning) scanTimer = setTimeout(scanLoop,250);
  };

  const imageFileToCanvas = (file, maxSize = 1400) => new Promise((resolve, reject) => {
    const image = new Image();
    const url = URL.createObjectURL(file);

    image.onload = () => {
      URL.revokeObjectURL(url);
      const scale = Math.min(1, maxSize / Math.max(image.naturalWidth, image.naturalHeight));
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
      canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
      const context = canvas.getContext('2d', { willReadFrequently: true });
      context.drawImage(image, 0, 0, canvas.width, canvas.height);
      resolve(canvas);
    };

    image.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('The selected image could not be opened by this browser.'));
    };

    image.src = url;
  });

  const decodeCanvasWithJsQr = async (canvas) => {
    await loadJsQr();
    const context = canvas.getContext('2d', { willReadFrequently: true });
    if (!context) return '';
    const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
    const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
      inversionAttempts: 'attemptBoth',
    });
    return code && code.data ? code.data : '';
  };

  const cropCanvasCenter = (canvas, cropRatio) => {
    const cropped = document.createElement('canvas');
    const width = Math.max(1, Math.round(canvas.width * cropRatio));
    const height = Math.max(1, Math.round(canvas.height * cropRatio));
    cropped.width = width;
    cropped.height = height;
    const ctx = cropped.getContext('2d');
    if (!ctx) return canvas;

    const sx = Math.max(0, Math.round((canvas.width - width) / 2));
    const sy = Math.max(0, Math.round((canvas.height - height) / 2));
    ctx.drawImage(canvas, sx, sy, width, height, 0, 0, width, height);
    return cropped;
  };

  const scanImageFile = async (file) => {
    if (!file) return;
    if (fileScanInProgress || checking) { setStatus('Please wait for the current QR lookup to finish.', 'warning'); return; }
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
      setStatus('Choose a JPG, PNG or WEBP QR photo up to 10 MB.', 'error');
      if (photoInput) photoInput.value = '';
      return;
    }
    fileScanInProgress = true;
    await stopScanner('Reading QR from photo...');
    const version = cameraRequestVersion;
    try {
      const canvas = await imageFileToCanvas(file,1600);
      let decoded = await decodeCanvasWithJsQr(canvas);
      for (const ratio of [0.8,0.6,0.4]) {
        if (decoded || version !== cameraRequestVersion || document.hidden) break;
        decoded = await decodeCanvasWithJsQr(cropCanvasCenter(canvas,ratio));
      }
      if (version !== cameraRequestVersion || document.hidden) return;
      if (!decoded) throw new Error('No QR found');
      await verifyCode(decoded);
    } catch {
      if (version === cameraRequestVersion) setStatus('No QR code was found. Use a clear photo with the whole QR visible, or enter the resident ID.', 'error');
    } finally {
      fileScanInProgress = false;
      if (photoInput) photoInput.value = '';
    }
  };

  const startScanner = async () => {
    if (!video) return;
    const requestVersion = cameraRequestVersion;

    if (window.isSecureContext === false || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setStatus('Live camera needs a trusted HTTPS address on your phone. An HTTP computer IP address cannot use live video. Use "Scan from phone camera/photo" below to take a QR photo, or enter the resident ID.', 'warning');
      return;
    }

    stopBtn.disabled = false;
    setStatus('Allow camera access when your browser asks. You can tap Stop to cancel.', 'info');

    try {
      try { detector = 'BarcodeDetector' in window ? new BarcodeDetector({formats:['qr_code']}) : null; } catch { detector = null; }
      if (!detector) await loadJsQr();
      if (requestVersion !== cameraRequestVersion || document.hidden) return;
      const requestedStream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: { ideal: 'environment' },
          width: { ideal: 1280 },
          height: { ideal: 720 }
        },
        audio: false
      });
      if (requestVersion !== cameraRequestVersion || document.hidden) {
        requestedStream.getTracks().forEach(track => track.stop());
        return;
      }
      stream = requestedStream;
      video.muted = true;
      video.playsInline = true;
      video.srcObject = stream;
      await video.play();
      if (requestVersion !== cameraRequestVersion || document.hidden) {
        await stopScanner('Camera paused. Tap Start scanner when you return.');
        return;
      }
      setScannerState(true);
      setStatus('Scanning... point the camera at the resident QR code.', 'info');
      scanLoop();
    } catch (error) {
      if (requestVersion !== cameraRequestVersion) return;
      const message = error.name === 'NotAllowedError' || error.name === 'SecurityError'
        ? 'Camera permission is blocked. Allow Camera in this site’s browser settings and in your phone’s browser permissions, then try again.'
        : error.name === 'NotFoundError' ? 'No camera was found. Use a QR photo or enter the resident ID.'
        : error.name === 'NotReadableError' ? 'The camera is busy or unavailable. Close other camera apps and browser tabs, then try again.'
        : error.message?.includes('QR reader') ? error.message
        : 'The camera could not start. Reopen this page in your phone’s browser, or use a QR photo.';
      await stopScanner(message);
      setStatus(message, 'error');
    }
  };

  if (startBtn) {
    startBtn.addEventListener('click', async () => {
      if (scannerStarting || scanning || fileScanInProgress || checking) return;
      scannerStarting = true;
      startBtn.disabled = true;
      try { await startScanner(); }
      finally { scannerStarting = false; startBtn.disabled = scanning; }
    });
  }

  if (stopBtn) {
    stopBtn.addEventListener('click', () => stopScanner());
  }

  if (photoInput) {
    photoInput.addEventListener('click', () => {
      stopScanner('Take or choose a clear photo showing the whole resident QR code.');
      photoInput.value = '';
    });
    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0];
      scanImageFile(file);
    });
  }

  if (form) {
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      verifyCode(input ? input.value : '');
    });
  }

  window.addEventListener('beforeunload', () => stopScanner(''));
  window.addEventListener('pagehide', () => stopScanner(''));
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopScanner('Camera paused. Tap Start scanner when you return.');
  });
  if (window.isSecureContext === false) {
    setStatus('Live camera needs a trusted HTTPS address on your phone. You can still use "Scan from phone camera/photo" or enter the resident ID.', 'warning');
  }
})();
