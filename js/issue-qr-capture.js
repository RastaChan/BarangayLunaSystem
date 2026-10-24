(() => {
  'use strict';
  document.querySelectorAll('[data-issue-qr-capture]').forEach(root => {
    const container = root.closest('[data-issue-resident-lookup]');
    const start = root.querySelector('[data-issue-camera-start]');
    const stop = root.querySelector('[data-issue-camera-stop]');
    const photo = root.querySelector('[data-issue-photo-open]');
    const fileInput = root.querySelector('[data-issue-photo-input]');
    const panel = root.querySelector('[data-issue-camera-panel]');
    const video = root.querySelector('video');
    const status = root.querySelector('[data-issue-camera-status]');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', {willReadFrequently:true});
    let stream = null, timer = null, generation = 0, starting = false, readingPhoto = false;
    function close(message = '') {
      generation++;
      if (timer !== null) clearTimeout(timer);
      timer = null;
      if (stream) stream.getTracks().forEach(track => track.stop());
      stream = null; video.srcObject = null; panel.hidden = true;
      starting = false; start.disabled = false; stop.disabled = false;
      if (message) status.textContent = message;
    }
    function decode(source, width, height, maxSize) {
      if (!width || !height) return null;
      if (typeof window.jsQR !== 'function' || !context) throw new Error('The QR reader could not load. Refresh this page and try again.');
      const scale = Math.min(1, maxSize / Math.max(width, height));
      canvas.width = Math.max(1, Math.round(width * scale)); canvas.height = Math.max(1, Math.round(height * scale));
      context.drawImage(source,0,0,canvas.width,canvas.height);
      const pixels = context.getImageData(0,0,canvas.width,canvas.height);
      return window.jsQR(pixels.data,pixels.width,pixels.height,{inversionAttempts:'attemptBoth'})?.data || null;
    }
    function found(code) {
      close('QR read successfully.');
      container.dispatchEvent(new CustomEvent('resident-qr-decoded',{detail:code}));
    }
    function frame(version) {
      if (version !== generation || !stream || document.hidden) return;
      try {
        if (video.readyState >= 2) {
          const code = decode(video,video.videoWidth,video.videoHeight,960);
          if (code) { found(code); return; }
        }
      } catch (error) { close(error.message); return; }
      timer = setTimeout(() => frame(version),180);
    }
    start.addEventListener('click',async () => {
      if (starting || stream || readingPhoto) return;
      if (container.dataset.lookupBusy === 'true') { status.textContent = 'Wait for the current resident lookup to finish.'; return; }
      close();
      if (window.isSecureContext === false || !navigator.mediaDevices?.getUserMedia) {
        status.textContent = 'Live camera needs a trusted HTTPS address on your phone. An HTTP computer IP address cannot use live video. Use Read QR photo or enter the Resident ID.';
        return;
      }
      starting = true; start.disabled = true; panel.hidden = false;
      status.textContent = 'Allow camera access, then hold the resident QR in front of the camera.';
      const version = generation;
      try {
        const camera = await navigator.mediaDevices.getUserMedia({audio:false,video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}}});
        if (version !== generation || document.hidden) { camera.getTracks().forEach(track => track.stop()); return; }
        stream = camera; video.muted = true; video.playsInline = true; video.srcObject = camera;
        await video.play();
        if (version !== generation) return;
        starting = false;
        status.textContent = 'Point the camera at the resident QR. Keep the whole code visible and steady.';
        frame(version);
      } catch (error) {
        if (version !== generation) return;
        const message = error.name === 'NotAllowedError' || error.name === 'SecurityError' ? 'Camera permission was denied or blocked. Allow Camera in this site’s browser settings and your phone’s browser permissions, or use Read QR photo.'
          : error.name === 'NotFoundError' ? 'No camera was found. Use Read QR photo or enter the Resident ID.'
          : 'The camera could not start. Close other camera apps and try again, or use Read QR photo.';
        close(message);
      }
    });
    stop.addEventListener('click',() => close('Camera stopped.'));
    photo.addEventListener('click',() => {
      if (container.dataset.lookupBusy === 'true') { status.textContent = 'Wait for the current resident lookup to finish.'; return; }
      if (!readingPhoto) { close('Choose a clear image of the resident QR.'); fileInput.click(); }
    });
    fileInput.addEventListener('change',async () => {
      const file = fileInput.files?.[0]; fileInput.value = '';
      if (!file || readingPhoto) return;
      if (container.dataset.lookupBusy === 'true') { status.textContent = 'Wait for the current resident lookup to finish.'; return; }
      close();
      if (file.size > 10*1024*1024 || !['image/png','image/jpeg','image/webp'].includes(file.type)) {
        status.textContent = 'Choose a JPG, PNG, or WEBP image up to 10 MB.'; return;
      }
      const version = generation;
      readingPhoto = true; photo.disabled = true; start.disabled = true;
      status.textContent = 'Reading QR photo…';
      let url;
      try {
        url = URL.createObjectURL(file);
        const image = new Image();
        await new Promise((resolve,reject) => { image.onload=resolve; image.onerror=()=>reject(new Error('The image could not be opened. Choose a different photo.')); image.src=url; });
        if (version !== generation || document.hidden) return;
        const code = decode(image,image.naturalWidth,image.naturalHeight,1600);
        if (!code) throw new Error('No QR was found. Crop the photo around the whole QR code or choose a clearer image.');
        found(code);
      } catch (error) { if (version === generation) status.textContent = error.message; }
      finally { if (url) URL.revokeObjectURL(url); readingPhoto = false; photo.disabled = false; start.disabled = false; }
    });
    container.addEventListener('resident-search-reset',() => { close(); status.textContent = ''; });
    document.addEventListener('visibilitychange',() => { if (document.hidden) close('Camera stopped while this page is hidden.'); });
    window.addEventListener('pagehide',() => close());
  });
})();
