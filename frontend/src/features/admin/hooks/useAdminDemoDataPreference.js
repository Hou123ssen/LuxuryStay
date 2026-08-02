import { useEffect, useState } from 'react';

const STORAGE_KEY = 'luxurrstay:admin:include-demo-data';

export function useAdminDemoDataPreference(defaultValue = true) {
  const [includeDemo, setIncludeDemo] = useState(() => {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === null) return defaultValue;
    return stored === 'true';
  });

  useEffect(() => {
    localStorage.setItem(STORAGE_KEY, includeDemo ? 'true' : 'false');
  }, [includeDemo]);

  return [includeDemo, setIncludeDemo];
}
