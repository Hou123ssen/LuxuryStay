import { useCallback, useEffect, useRef, useState } from 'react';

const jitsiScriptPromises = new Map();

function loadJitsiScript(scriptUrl) {
  if (window.JitsiMeetExternalAPI) return Promise.resolve();
  if (jitsiScriptPromises.has(scriptUrl)) return jitsiScriptPromises.get(scriptUrl);

  const existingScript = document.querySelector(`script[src="${scriptUrl}"]`);
  if (existingScript) {
    const promise = new Promise((resolve, reject) => {
      existingScript.addEventListener('load', () => {
        window.JitsiMeetExternalAPI ? resolve() : reject(new Error('Jitsi API unavailable.'));
      }, { once: true });
      existingScript.addEventListener('error', () => reject(new Error('Jitsi script failed.')), { once: true });
    });
    jitsiScriptPromises.set(scriptUrl, promise);
    return promise;
  }

  const promise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = scriptUrl;
    script.async = true;
    script.dataset.jitsiExternalApi = 'true';
    script.onload = () => {
      window.JitsiMeetExternalAPI ? resolve() : reject(new Error('Jitsi API unavailable.'));
    };
    script.onerror = () => reject(new Error('Jitsi script failed.'));
    document.body.appendChild(script);
  });

  jitsiScriptPromises.set(scriptUrl, promise);
  return promise;
}

export function useJitsiAudioCall({ callSession, parentRef, userName, debug = false, onMutedChange }) {
  const apiRef = useRef(null);
  const [providerStatus, setProviderStatus] = useState('idle');
  const [providerError, setProviderError] = useState('');
  const [isScriptLoaded, setIsScriptLoaded] = useState(!!window.JitsiMeetExternalAPI);
  const [isApiCreated, setIsApiCreated] = useState(false);
  const [lastProviderEvent, setLastProviderEvent] = useState('');

  const trackEvent = useCallback((name, payload = null) => {
    setLastProviderEvent(name);
    if (debug) console.info('[LuxurrStay audio provider]', name, payload || '');
  }, [debug]);

  const disposeProvider = useCallback(() => {
    if (!apiRef.current) return;

    try {
      apiRef.current.dispose();
    } catch (err) {
      console.error('Failed to dispose audio provider:', err);
    } finally {
      apiRef.current = null;
      setIsApiCreated(false);
    }
  }, []);

  const hangUpProvider = useCallback(() => {
    if (!apiRef.current) return;

    try {
      apiRef.current.executeCommand('hangup');
    } catch (err) {
      console.error('Failed to hang up audio provider:', err);
    } finally {
      disposeProvider();
    }
  }, [disposeProvider]);

  const toggleAudio = useCallback(() => {
    if (!apiRef.current) return false;

    try {
      apiRef.current.executeCommand('toggleAudio');
      return true;
    } catch (err) {
      console.error('Failed to toggle provider audio:', err);
      return false;
    }
  }, []);

  useEffect(() => {
    let active = true;

    if (!callSession) {
      setProviderStatus('idle');
      return () => { active = false; };
    }

    if (!callSession.script_url || !callSession.domain || !callSession.room_name || !parentRef.current) {
      setProviderStatus('error');
      setProviderError('Unable to connect audio provider. Please try again.');
      return () => { active = false; };
    }

    setProviderStatus('loading-script');
    setProviderError('');
    setLastProviderEvent('');

    loadJitsiScript(callSession.script_url)
      .then(() => {
        if (!active || !parentRef.current || apiRef.current) return;
        setIsScriptLoaded(true);
        setProviderStatus('connecting');
        trackEvent('scriptLoaded');

        try {
          const api = new window.JitsiMeetExternalAPI(callSession.domain, {
            roomName: callSession.room_name,
            parentNode: parentRef.current,
            width: '100%',
            height: '100%',
            userInfo: userName ? { displayName: userName } : undefined,
            configOverwrite: {
              startAudioOnly: true,
              startWithVideoMuted: true,
              disableDeepLinking: true,
              prejoinConfig: { enabled: false },
            },
            interfaceConfigOverwrite: {
              TOOLBAR_BUTTONS: [],
              DISABLE_JOIN_LEAVE_NOTIFICATIONS: true,
              HIDE_INVITE_MORE_HEADER: true,
            },
          });

          apiRef.current = api;
          setIsApiCreated(true);
          trackEvent('apiCreated');

          const improveIframePermissions = () => {
            const iframe = parentRef.current?.querySelector('iframe');
            if (!iframe) return;

            const currentAllow = iframe.getAttribute('allow') || '';
            const requiredAllow = ['microphone', 'camera', 'fullscreen', 'display-capture', 'autoplay'];
            const allow = Array.from(new Set([
              ...currentAllow.split(';').map(value => value.trim()).filter(Boolean),
              ...requiredAllow,
            ])).join('; ');

            iframe.setAttribute('allow', allow);
          };

          improveIframePermissions();
          window.setTimeout(improveIframePermissions, 0);

          api.addEventListener?.('videoConferenceJoined', (event) => {
            if (!active) return;
            setProviderStatus('ready');
            trackEvent('videoConferenceJoined', event);
            try {
              api.executeCommand('setAudioOnly', true);
            } catch {}
          });

          api.addEventListener?.('audioAvailabilityChanged', (event) => {
            if (!active) return;
            if (event?.available === false) setProviderStatus('waiting-for-microphone');
            trackEvent('audioAvailabilityChanged', event);
          });

          api.addEventListener?.('audioMuteStatusChanged', ({ muted }) => {
            if (active) onMutedChange?.(!!muted);
            trackEvent('audioMuteStatusChanged', { muted });
          });

          api.addEventListener?.('participantJoined', (event) => {
            if (!active) return;
            trackEvent('participantJoined', event);
          });

          api.addEventListener?.('participantLeft', (event) => {
            if (!active) return;
            trackEvent('participantLeft', event);
          });

          api.addEventListener?.('readyToClose', () => {
            trackEvent('readyToClose');
            if (active) disposeProvider();
          });

          api.addEventListener?.('errorOccurred', (event) => {
            if (!active) return;
            trackEvent('errorOccurred', event);
            setProviderStatus('error');
            setProviderError(event?.message || 'Unable to connect audio provider. Please try again.');
          });

          api.addEventListener?.('conferenceFailed', (event) => {
            if (!active) return;
            trackEvent('conferenceFailed', event);
            setProviderStatus('error');
            setProviderError(event?.message || 'Unable to connect audio provider. Please try again.');
          });

          api.addEventListener?.('connectionFailed', (event) => {
            if (!active) return;
            trackEvent('connectionFailed', event);
            setProviderStatus('error');
            setProviderError(event?.message || 'Unable to connect audio provider. Please try again.');
          });
        } catch (err) {
          console.error('Failed to create audio provider:', err);
          if (!active) return;
          setProviderStatus('error');
          setProviderError('Unable to connect audio provider. Please try again.');
        }
      })
      .catch((err) => {
        console.error('Failed to load audio provider:', err);
        if (!active) return;
        trackEvent('scriptLoadFailed', err);
        setProviderStatus('error');
        setProviderError(err?.message || 'Unable to connect audio provider. Please try again.');
      });

    return () => {
      active = false;
      disposeProvider();
    };
  }, [callSession, disposeProvider, onMutedChange, parentRef, trackEvent, userName]);

  return {
    providerStatus,
    providerError,
    isScriptLoaded,
    isApiCreated,
    lastProviderEvent,
    isProviderReady: providerStatus === 'ready',
    toggleAudio,
    hangUpProvider,
  };
}
