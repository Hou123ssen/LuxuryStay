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

  const disposeProvider = useCallback(() => {
    if (!apiRef.current) return;

    try {
      apiRef.current.dispose();
    } catch (err) {
      console.error('Failed to dispose audio provider:', err);
    } finally {
      apiRef.current = null;
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

    setProviderStatus('connecting');
    setProviderError('');

    loadJitsiScript(callSession.script_url)
      .then(() => {
        if (!active || !parentRef.current || apiRef.current) return;

        try {
          const api = new window.JitsiMeetExternalAPI(callSession.domain, {
            roomName: callSession.room_name,
            parentNode: parentRef.current,
            width: debug ? '100%' : 1,
            height: debug ? '100%' : 1,
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

          api.addEventListener?.('videoConferenceJoined', () => {
            if (!active) return;
            setProviderStatus('ready');
            try {
              api.executeCommand('setAudioOnly', true);
            } catch {}
          });

          api.addEventListener?.('audioMuteStatusChanged', ({ muted }) => {
            if (active) onMutedChange?.(!!muted);
          });

          api.addEventListener?.('participantLeft', () => {
            if (active) setProviderStatus('ready');
          });

          api.addEventListener?.('readyToClose', () => {
            if (active) disposeProvider();
          });

          api.addEventListener?.('errorOccurred', () => {
            if (!active) return;
            setProviderStatus('error');
            setProviderError('Unable to connect audio provider. Please try again.');
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
        setProviderStatus('error');
        setProviderError('Unable to connect audio provider. Please try again.');
      });

    return () => {
      active = false;
      disposeProvider();
    };
  }, [callSession, debug, disposeProvider, onMutedChange, parentRef, userName]);

  return {
    providerStatus,
    providerError,
    isProviderReady: providerStatus === 'ready',
    toggleAudio,
    hangUpProvider,
  };
}
