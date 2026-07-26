import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const LIB_JITSI_URL = 'https://kmeet.infomaniak.com/libs/lib-jitsi-meet.min.js';
const KMEET_WEBSOCKET_URL = 'wss://kmeet.infomaniak.com/xmpp-websocket';
const KMEET_BOSH_URL = 'https://kmeet.infomaniak.com/http-bind';
const KMEET_FOCUS_USER_JID = 'focus@auth.kmeet.infomaniak.com';
const KMEET_CLIENT_NODE = 'http://jitsi.org/jitsimeet';
const libJitsiScriptPromises = new Map();

function loadLibJitsiScript() {
  if (window.JitsiMeetJS) return Promise.resolve();
  if (libJitsiScriptPromises.has(LIB_JITSI_URL)) return libJitsiScriptPromises.get(LIB_JITSI_URL);

  const existingScript = document.querySelector(`script[src="${LIB_JITSI_URL}"]`);
  if (existingScript) {
    const promise = new Promise((resolve, reject) => {
      existingScript.addEventListener('load', () => {
        window.JitsiMeetJS ? resolve() : reject(new Error('lib-jitsi-meet unavailable.'));
      }, { once: true });
      existingScript.addEventListener('error', () => reject(new Error('lib-jitsi-meet script failed.')), { once: true });
    });
    libJitsiScriptPromises.set(LIB_JITSI_URL, promise);
    return promise;
  }

  const promise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.src = LIB_JITSI_URL;
    script.async = true;
    script.dataset.libJitsiMeet = 'true';
    script.onload = () => {
      window.JitsiMeetJS ? resolve() : reject(new Error('lib-jitsi-meet unavailable.'));
    };
    script.onerror = () => reject(new Error('lib-jitsi-meet script failed.'));
    document.body.appendChild(script);
  });

  libJitsiScriptPromises.set(LIB_JITSI_URL, promise);
  return promise;
}

function safeErrorMessage(error) {
  if (typeof error === 'string') return error;
  return error?.message || error?.name || error?.type || 'Unknown provider error.';
}

function safeDiagnosticValue(value) {
  if (value == null) return value;
  if (typeof value === 'string') return value.slice(0, 180);
  if (typeof value === 'number' || typeof value === 'boolean') return value;
  if (value instanceof Error) {
    return {
      name: value.name,
      message: value.message?.slice(0, 180),
    };
  }

  if (typeof value === 'object') {
    const safe = {};
    ['name', 'message', 'type', 'code', 'error', 'reason', 'details'].forEach((key) => {
      if (value[key] == null) return;
      safe[key] = safeDiagnosticValue(value[key]);
    });
    return Object.keys(safe).length ? safe : '[object]';
  }

  return String(value).slice(0, 180);
}

function safeDiagnosticArgs(args) {
  return args.map(arg => safeDiagnosticValue(arg));
}

function createHiddenAudioElement(trackId) {
  const audio = document.createElement('audio');
  audio.autoplay = true;
  audio.playsInline = true;
  audio.dataset.libJitsiTrackId = trackId;
  audio.style.position = 'absolute';
  audio.style.width = '1px';
  audio.style.height = '1px';
  audio.style.opacity = '0';
  audio.style.pointerEvents = 'none';
  audio.style.left = '-9999px';
  audio.setAttribute('aria-hidden', 'true');
  document.body.appendChild(audio);
  return audio;
}

export function useLibJitsiAudioCall({ callSession, userName, enabled = false, debug = false, transport = 'websocket' }) {
  const connectionRef = useRef(null);
  const conferenceRef = useRef(null);
  const localTrackRef = useRef(null);
  const remoteAudioRefs = useRef(new Map());
  const mountedRef = useRef(false);
  const cleaningUpRef = useRef(false);
  const cleanupRef = useRef(() => {});
  const conferenceCleanupRef = useRef(() => {});
  const [providerStatus, setProviderStatus] = useState('idle');
  const [providerError, setProviderError] = useState('');
  const [isScriptLoaded, setIsScriptLoaded] = useState(!!window.JitsiMeetJS);
  const [isMuted, setIsMuted] = useState(false);
  const [remoteAudioCount, setRemoteAudioCount] = useState(0);
  const [lastProviderEvent, setLastProviderEvent] = useState('');
  const [lastSafeError, setLastSafeError] = useState('');
  const [isLocalTrackCreated, setIsLocalTrackCreated] = useState(false);
  const [conferenceStatus, setConferenceStatus] = useState('idle');
  const [requiresPlaybackGesture, setRequiresPlaybackGesture] = useState(false);
  const [selectedTransport, setSelectedTransport] = useState(transport === 'bosh' ? 'bosh' : 'websocket');
  const [connectionEstablished, setConnectionEstablished] = useState(false);
  const [conferenceJoinInProgress, setConferenceJoinInProgress] = useState(false);
  const [lastFailureEvent, setLastFailureEvent] = useState('');
  const [lastFailureArgs, setLastFailureArgs] = useState([]);
  const [connectionFailedCode, setConnectionFailedCode] = useState('');

  const setSafeState = useCallback((setter, value) => {
    if (mountedRef.current) setter(value);
  }, []);

  const trackEvent = useCallback((name, payload = null) => {
    setSafeState(setLastProviderEvent, name);
    if (debug) console.info('[LuxurrStay lib-jitsi audio]', name, payload || '');
  }, [debug, setSafeState]);

  const setSafeError = useCallback((error) => {
    const message = safeErrorMessage(error);
    setSafeState(setLastSafeError, message);
    setSafeState(setProviderError, 'Unable to connect the secure audio call.');
    setSafeState(setProviderStatus, 'error');
    if (debug) console.error('[LuxurrStay lib-jitsi audio]', message, error);
  }, [debug, setSafeState]);

  const captureFailure = useCallback((eventName, args) => {
    const safeArgs = safeDiagnosticArgs(args);
    setSafeState(setLastFailureEvent, eventName);
    setSafeState(setLastFailureArgs, safeArgs);

    const firstCode = safeArgs.find(arg => typeof arg === 'string')
      || safeArgs.find(arg => arg?.code)?.code
      || safeArgs.find(arg => arg?.error)?.error
      || '';

    if (eventName === 'connectionFailed') {
      setSafeState(setConnectionFailedCode, String(firstCode || 'unknown'));
    }

    if (debug) console.warn('[LuxurrStay lib-jitsi audio]', eventName, safeArgs);
  }, [debug, setSafeState]);

  const detachRemoteTrack = useCallback((track) => {
    const trackId = track?.getId?.();
    if (!trackId) return;

    const entry = remoteAudioRefs.current.get(trackId);
    if (!entry) return;

    try {
      track.detach?.(entry.audio);
    } catch (err) {
      if (debug) console.warn('[LuxurrStay lib-jitsi audio] failed to detach remote track', err);
    }

    entry.audio.pause();
    entry.audio.removeAttribute('src');
    entry.audio.srcObject = null;
    entry.audio.remove();
    remoteAudioRefs.current.delete(trackId);
    setSafeState(setRemoteAudioCount, remoteAudioRefs.current.size);
  }, [debug, setSafeState]);

  const detachRemoteTracksForParticipant = useCallback((participantId) => {
    if (!participantId) return;

    Array.from(remoteAudioRefs.current.entries()).forEach(([trackId, entry]) => {
      if (entry.participantId !== participantId) return;
      detachRemoteTrack(entry.track || { getId: () => trackId });
    });
  }, [detachRemoteTrack]);

  const cleanupProvider = useCallback(async () => {
    if (cleaningUpRef.current) return;
    cleaningUpRef.current = true;
    trackEvent('cleanupStarted');

    const conference = conferenceRef.current;
    const connection = connectionRef.current;
    const localTrack = localTrackRef.current;
    conferenceRef.current = null;
    connectionRef.current = null;
    localTrackRef.current = null;

    conferenceCleanupRef.current?.();
    conferenceCleanupRef.current = () => {};
    cleanupRef.current?.();
    cleanupRef.current = () => {};

    remoteAudioRefs.current.forEach((entry) => {
      try {
        entry.track?.detach?.(entry.audio);
      } catch {}
      entry.audio.pause();
      entry.audio.removeAttribute('src');
      entry.audio.srcObject = null;
      entry.audio.remove();
    });
    remoteAudioRefs.current.clear();
    setSafeState(setRemoteAudioCount, 0);

    if (localTrack) {
      try {
        conference?.removeTrack?.(localTrack);
      } catch {}
      try {
        localTrack.dispose?.();
      } catch (err) {
        if (debug) console.warn('[LuxurrStay lib-jitsi audio] failed to dispose local track', err);
      }
      setSafeState(setIsLocalTrackCreated, false);
    }

    if (conference) {
      try {
        await conference.leave?.();
      } catch (err) {
        if (debug) console.warn('[LuxurrStay lib-jitsi audio] failed to leave conference', err);
      }
    }

    if (connection) {
      try {
        connection.disconnect?.();
      } catch (err) {
        if (debug) console.warn('[LuxurrStay lib-jitsi audio] failed to disconnect', err);
      }
    }

    setSafeState(setConferenceStatus, 'disconnected');
    setSafeState(setProviderStatus, 'disconnected');
    cleaningUpRef.current = false;
    trackEvent('cleanupFinished');
  }, [debug, setSafeState, trackEvent]);

  const attachRemoteTrack = useCallback((track) => {
    if (track?.isLocal?.() || track?.getType?.() !== 'audio') return;

    const trackId = track.getId?.() || `${track.getParticipantId?.() || 'remote'}-${Date.now()}`;
    let entry = remoteAudioRefs.current.get(trackId);
    if (!entry) {
      const audio = createHiddenAudioElement(trackId);
      entry = {
        audio,
        track,
        participantId: track.getParticipantId?.(),
      };
      remoteAudioRefs.current.set(trackId, entry);
      setSafeState(setRemoteAudioCount, remoteAudioRefs.current.size);
    }

    try {
      track.attach?.(entry.audio);
      const playPromise = entry.audio.play?.();
      if (playPromise?.catch) {
        playPromise.catch(() => {
          setSafeState(setRequiresPlaybackGesture, true);
          trackEvent('remoteAudioAutoplayBlocked');
        });
      }
      trackEvent('remoteAudioTrackAttached', { trackId });
    } catch (err) {
      setSafeError(err);
    }
  }, [setSafeError, setSafeState, trackEvent]);

  const toggleAudio = useCallback(async () => {
    const localTrack = localTrackRef.current;
    if (!localTrack) return false;

    try {
      if (localTrack.isMuted?.()) {
        await localTrack.unmute?.();
      } else {
        await localTrack.mute?.();
      }

      const muted = !!localTrack.isMuted?.();
      setSafeState(setIsMuted, muted);
      trackEvent('localAudioMuteChanged', { muted });
      return true;
    } catch (err) {
      setSafeError(err);
      return false;
    }
  }, [setSafeError, setSafeState, trackEvent]);

  const hangUpProvider = useCallback(() => {
    cleanupProvider();
  }, [cleanupProvider]);

  useEffect(() => {
    mountedRef.current = true;
    return () => {
      mountedRef.current = false;
    };
  }, []);

  useEffect(() => {
    if (!enabled) {
      setProviderStatus('idle');
      setConferenceStatus('idle');
      return undefined;
    }

    if (!callSession?.room_name) {
      setProviderStatus('idle');
      setConferenceStatus('idle');
      return undefined;
    }

    let active = true;
    cleaningUpRef.current = false;
    setProviderError('');
    setLastSafeError('');
    setLastFailureEvent('');
    setLastFailureArgs([]);
    setConnectionFailedCode('');
    setConnectionEstablished(false);
    setConferenceJoinInProgress(false);
    setRequiresPlaybackGesture(false);
    setRemoteAudioCount(0);
    setIsLocalTrackCreated(false);
    setProviderStatus('loading-library');
    setConferenceStatus('idle');
    setSelectedTransport(transport === 'bosh' ? 'bosh' : 'websocket');
    trackEvent('loadingLibrary');

    loadLibJitsiScript()
      .then(async () => {
        if (!active) return;
        setSafeState(setIsScriptLoaded, true);

        const JitsiMeetJS = window.JitsiMeetJS;
        if (!JitsiMeetJS) throw new Error('lib-jitsi-meet unavailable.');

        JitsiMeetJS.setLogLevel?.(JitsiMeetJS.logLevels?.ERROR || 'error');
        JitsiMeetJS.init?.({
          disableAudioLevels: true,
          disableThirdPartyRequests: true,
        });

        setSafeState(setProviderStatus, 'requesting-microphone');
        trackEvent('requestingMicrophone');
        const tracks = await JitsiMeetJS.createLocalTracks({ devices: ['audio'] });
        if (!active) {
          tracks.forEach(track => track.dispose?.());
          return;
        }

        const localAudioTrack = tracks.find(track => track.getType?.() === 'audio');
        if (!localAudioTrack) throw new Error('No local audio track was created.');

        localTrackRef.current = localAudioTrack;
        setSafeState(setIsLocalTrackCreated, true);
        setSafeState(setIsMuted, !!localAudioTrack.isMuted?.());

        setSafeState(setProviderStatus, 'connecting');
        trackEvent('connecting');

        const normalizedTransport = transport === 'bosh' ? 'bosh' : 'websocket';
        const options = {
          hosts: {
            domain: 'kmeet.infomaniak.com',
            muc: 'muc.kmeet.infomaniak.com',
          },
          focusUserJid: KMEET_FOCUS_USER_JID,
          bosh: KMEET_BOSH_URL,
          clientNode: KMEET_CLIENT_NODE,
          p2p: { enabled: false },
        };

        if (normalizedTransport === 'bosh') {
          options.serviceUrl = KMEET_BOSH_URL;
        } else {
          options.serviceUrl = KMEET_WEBSOCKET_URL;
          options.websocket = KMEET_WEBSOCKET_URL;
        }

        const connection = new JitsiMeetJS.JitsiConnection(null, null, options);
        connectionRef.current = connection;

        const connectionEvents = JitsiMeetJS.events.connection;
        const conferenceEvents = JitsiMeetJS.events.conference;

        const onConnectionEstablished = () => {
          if (!active) return;
          setSafeState(setProviderStatus, 'joining');
          setSafeState(setConferenceStatus, 'joining');
          setSafeState(setConnectionEstablished, true);
          setSafeState(setConferenceJoinInProgress, true);
          trackEvent('connectionEstablished');

          const conference = connection.initJitsiConference(callSession.room_name, {
            openBridgeChannel: 'websocket',
            p2p: { enabled: false },
          });
          conferenceRef.current = conference;

          const onTrackAdded = attachRemoteTrack;
          const onTrackRemoved = detachRemoteTrack;
          const onConferenceJoined = async () => {
            if (!active) return;
            setSafeState(setConferenceStatus, 'joined');
            setSafeState(setProviderStatus, 'ready');
            setSafeState(setConferenceJoinInProgress, false);
            trackEvent('conferenceJoined');
            try {
              await conference.addTrack(localAudioTrack);
              trackEvent('localAudioTrackAdded');
            } catch (err) {
              setSafeError(err);
            }
          };
          const onConferenceFailed = (...args) => {
            if (!active) return;
            trackEvent('conferenceFailed', safeDiagnosticArgs(args));
            captureFailure('conferenceFailed', args);
            setSafeState(setConferenceJoinInProgress, false);
            setSafeError(args[0]);
          };
          const onConferenceError = (...args) => {
            if (!active) return;
            trackEvent('conferenceError', safeDiagnosticArgs(args));
            captureFailure('conferenceError', args);
            setSafeError(args[0]);
          };
          const onUserLeft = (id) => {
            if (!active) return;
            trackEvent('participantLeft', { id });
            detachRemoteTracksForParticipant(id);
          };
          const onTrackMuteChanged = (track) => {
            if (track === localAudioTrack || track?.isLocal?.()) {
              setSafeState(setIsMuted, !!track.isMuted?.());
            }
          };

          conference.on(conferenceEvents.TRACK_ADDED, onTrackAdded);
          conference.on(conferenceEvents.TRACK_REMOVED, onTrackRemoved);
          conference.on(conferenceEvents.CONFERENCE_JOINED, onConferenceJoined);
          conference.on(conferenceEvents.CONFERENCE_FAILED, onConferenceFailed);
          if (conferenceEvents.CONFERENCE_ERROR) {
            conference.on(conferenceEvents.CONFERENCE_ERROR, onConferenceError);
          }
          conference.on(conferenceEvents.USER_LEFT, onUserLeft);
          conference.on(conferenceEvents.TRACK_MUTE_CHANGED, onTrackMuteChanged);

          conferenceCleanupRef.current = () => {
            conference.off?.(conferenceEvents.TRACK_ADDED, onTrackAdded);
            conference.off?.(conferenceEvents.TRACK_REMOVED, onTrackRemoved);
            conference.off?.(conferenceEvents.CONFERENCE_JOINED, onConferenceJoined);
            conference.off?.(conferenceEvents.CONFERENCE_FAILED, onConferenceFailed);
            if (conferenceEvents.CONFERENCE_ERROR) {
              conference.off?.(conferenceEvents.CONFERENCE_ERROR, onConferenceError);
            }
            conference.off?.(conferenceEvents.USER_LEFT, onUserLeft);
            conference.off?.(conferenceEvents.TRACK_MUTE_CHANGED, onTrackMuteChanged);
          };

          if (userName) conference.setDisplayName?.(userName);
          conference.join();
        };

        const onConnectionFailed = (...args) => {
          if (!active) return;
          trackEvent('connectionFailed', safeDiagnosticArgs(args));
          captureFailure('connectionFailed', args);
          setSafeError(args[0]);
        };

        const onConnectionDisconnected = () => {
          if (!active) return;
          setSafeState(setProviderStatus, 'disconnected');
          setSafeState(setConferenceStatus, 'disconnected');
          setSafeState(setConferenceJoinInProgress, false);
          trackEvent('connectionDisconnected');
        };

        connection.addEventListener(connectionEvents.CONNECTION_ESTABLISHED, onConnectionEstablished);
        connection.addEventListener(connectionEvents.CONNECTION_FAILED, onConnectionFailed);
        connection.addEventListener(connectionEvents.CONNECTION_DISCONNECTED, onConnectionDisconnected);

        cleanupRef.current = () => {
          connection.removeEventListener?.(connectionEvents.CONNECTION_ESTABLISHED, onConnectionEstablished);
          connection.removeEventListener?.(connectionEvents.CONNECTION_FAILED, onConnectionFailed);
          connection.removeEventListener?.(connectionEvents.CONNECTION_DISCONNECTED, onConnectionDisconnected);
        };

        connection.connect();
      })
      .catch((err) => {
        if (!active) return;
        trackEvent('libJitsiFailed', err);
        setSafeError(err);
      });

    return () => {
      active = false;
      cleanupRef.current?.();
      cleanupProvider();
    };
  }, [
    attachRemoteTrack,
    callSession?.room_name,
    cleanupProvider,
    detachRemoteTrack,
    detachRemoteTracksForParticipant,
    enabled,
    setSafeError,
    setSafeState,
    trackEvent,
    transport,
    userName,
  ]);

  const diagnostics = useMemo(() => ({
    engine: 'lib-jitsi-meet',
    scriptLoaded: isScriptLoaded,
    connectionStatus: providerStatus,
    conferenceStatus,
    roomName: callSession?.room_name || '',
    localAudioTrackCreated: isLocalTrackCreated,
    remoteAudioTrackCount: remoteAudioCount,
    lastEvent: lastProviderEvent,
    lastSafeError,
    selectedTransport,
    connectionEstablished,
    conferenceJoinInProgress,
    lastFailureEvent,
    lastFailureArgs,
    connectionFailedCode,
    focusUserJidConfigured: true,
    clientNode: KMEET_CLIENT_NODE,
    requiresPlaybackGesture,
  }), [
    callSession?.room_name,
    conferenceStatus,
    isLocalTrackCreated,
    isScriptLoaded,
    lastProviderEvent,
    lastSafeError,
    selectedTransport,
    connectionEstablished,
    conferenceJoinInProgress,
    lastFailureEvent,
    lastFailureArgs,
    connectionFailedCode,
    providerStatus,
    remoteAudioCount,
    requiresPlaybackGesture,
  ]);

  return {
    providerStatus,
    providerError,
    isProviderReady: providerStatus === 'ready',
    isMuted,
    toggleAudio,
    hangUpProvider,
    diagnostics,
  };
}
