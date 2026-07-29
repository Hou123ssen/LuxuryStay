let audioContext = null;
let ringtoneTimer = null;
let vibrationTimer = null;
let activeOscillators = [];

const ALERTS_STORAGE_KEY = 'luxurrstay_call_alerts_enabled';

function getAudioContext() {
  const AudioContextClass = window.AudioContext || window.webkitAudioContext;
  if (!AudioContextClass) return null;

  if (!audioContext) {
    audioContext = new AudioContextClass();
  }

  return audioContext;
}

function stopOscillators() {
  activeOscillators.forEach((oscillator) => {
    try {
      oscillator.stop();
    } catch {}
  });
  activeOscillators = [];
}

function playToneSequence(volume = 0.045) {
  const context = getAudioContext();
  if (!context) return;

  const now = context.currentTime;
  const gain = context.createGain();
  gain.gain.setValueAtTime(0.0001, now);
  gain.gain.exponentialRampToValueAtTime(volume, now + 0.03);
  gain.gain.exponentialRampToValueAtTime(0.0001, now + 1.05);
  gain.connect(context.destination);

  [0, 0.38].forEach((offset) => {
    const oscillator = context.createOscillator();
    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(660, now + offset);
    oscillator.frequency.exponentialRampToValueAtTime(880, now + offset + 0.18);
    oscillator.connect(gain);
    oscillator.start(now + offset);
    oscillator.stop(now + offset + 0.28);
    activeOscillators.push(oscillator);
  });
}

export async function enableIncomingCallAlerts() {
  const context = getAudioContext();
  if (!context) return false;

  if (context.state === 'suspended') {
    await context.resume();
  }

  playToneSequence(0.01);
  localStorage.setItem(ALERTS_STORAGE_KEY, 'true');
  return true;
}

export async function startIncomingCallAlert() {
  stopIncomingCallAlert();

  let soundBlocked = false;
  const soundEnabled = localStorage.getItem(ALERTS_STORAGE_KEY) === 'true';

  if (soundEnabled) {
    try {
      const context = getAudioContext();
      if (context?.state === 'suspended') await context.resume();
      playToneSequence();
      ringtoneTimer = window.setInterval(playToneSequence, 1600);
    } catch {
      soundBlocked = true;
    }
  }

  if (navigator.vibrate) {
    try {
      navigator.vibrate([300, 200, 300, 600]);
      vibrationTimer = window.setInterval(() => {
        navigator.vibrate([300, 200, 300, 600]);
      }, 1600);
    } catch {}
  }

  return { soundBlocked, soundEnabled };
}

export function stopIncomingCallAlert() {
  if (ringtoneTimer) {
    window.clearInterval(ringtoneTimer);
    ringtoneTimer = null;
  }

  if (vibrationTimer) {
    window.clearInterval(vibrationTimer);
    vibrationTimer = null;
  }

  stopOscillators();

  try {
    navigator.vibrate?.(0);
  } catch {}
}
