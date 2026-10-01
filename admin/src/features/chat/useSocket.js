import { useEffect, useRef, useState } from 'react';
import { useSelector } from 'react-redux';
import { io } from 'socket.io-client';

const URL = import.meta.env.VITE_REALTIME_URL || window.location.origin;

/** One shared socket for the panel's live chat (same JWT as the API). */
let shared = null;
let users = 0;

export default function useSocket() {
  const token = useSelector((s) => s.auth.token);
  const [connected, setConnected] = useState(!!shared?.connected);
  const ref = useRef(null);

  useEffect(() => {
    if (!token) return undefined;
    if (!shared) shared = io(URL, { auth: { token }, transports: ['websocket'] });
    users += 1;
    ref.current = shared;
    const on = () => setConnected(true);
    const off = () => setConnected(false);
    shared.on('connect', on);
    shared.on('disconnect', off);
    return () => {
      shared?.off('connect', on);
      shared?.off('disconnect', off);
      users -= 1;
      if (users === 0) { shared?.close(); shared = null; }
    };
  }, [token]);

  return { socket: ref.current || shared, connected };
}
