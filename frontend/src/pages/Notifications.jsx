import { useState, useEffect } from "react";
import api, { notificationService, bookingService , chatService } from "../shared/api/api";
import { useNavigate } from 'react-router-dom';

import { format } from "date-fns";
import {
  FiBell,
  FiCheck,
  FiX,
  FiCheckCircle,
  FiMessageCircle,
} from "react-icons/fi";
import toast from "react-hot-toast";

export default function Notifications() {
  const [notifs, setNotifs] = useState([]);
  const [loading, setLoad] = useState(true);
  const [actionLoad, setActionLoad] = useState(null);
  const navigate = useNavigate();


  const fetchNotifs = async () => {
    try {
      const res = await notificationService.list();
      setNotifs(res.data?.data || res.data);
    } catch {
      setNotifs([]);
    }
    setLoad(false);
  };

  useEffect(() => {
    fetchNotifs();
  }, []);

  const markAllRead = async () => {
    try {
      await api.put("/notifications/read-all");
      setNotifs((prev) => prev.map((n) => ({ ...n, read: true })));
    } catch {}
  };

  const markOneRead = async (id) => {
    try {
      await api.put(`/notifications/${id}/read`);
      setNotifs((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read: true } : n)),
      );
    } catch {}
  };

  const handleAccept = async (notif) => {
    if (!notif.booking_id) return;
    setActionLoad(`accept_${notif.id}`);
    try {
      await bookingService.accept(notif.booking_id);
      await markOneRead(notif.id);
      // تحديث الـ notification محلياً
      setNotifs((prev) =>
        prev.map((n) =>
          n.id === notif.id
            ? {
                ...n,
                read: true,
                type: "booking_accepted",
                message: "✅ You accepted this booking.",
              }
            : n,
        ),
      );
      toast.success("Booking accepted! ✅");
    } catch (err) {
      toast.error(err.response?.data?.error || "Failed to accept booking");
    }
    setActionLoad(null);
  };

  const handleReject = async (notif) => {
    if (!notif.booking_id) return;
    setActionLoad(`reject_${notif.id}`);
    try {
      await bookingService.reject(notif.booking_id);
      await markOneRead(notif.id);
      setNotifs((prev) =>
        prev.map((n) =>
          n.id === notif.id
            ? {
                ...n,
                read: true,
                type: "booking_rejected",
                message: "❌ You declined this booking.",
              }
            : n,
        ),
      );
      toast.success("Booking rejected.");
    } catch (err) {
      toast.error(err.response?.data?.error || "Failed to reject booking");
    }
    setActionLoad(null);
  };

  const unreadCount = notifs.filter((n) => !n.read).length;

  // أيقونة حسب نوع الـ notification
  const typeIcon = (type) => {
    switch (type) {
      case "booking_request":
        return "🔔";
      case "booking_accepted":
        return "✅";
      case "booking_rejected":
        return "❌";
      case "booking_pending":
        return "⏳";
      default:
        return "💬";
    }
  };

  return (
    <div className="min-h-screen px-4 py-10 max-w-2xl mx-auto">
      {/* Header */}
      <div className="mb-8 fade-up flex items-start justify-between flex-wrap gap-4">
        <div>
          <div className="ornament-divider mb-3 max-w-xs">
            <span className="text-xs tracking-[0.3em] text-gold/55 uppercase">
              Updates
            </span>
          </div>
          <h1 className="font-display text-4xl font-light text-cream">
            <span className="text-gold-gradient italic">Notifications</span>
          </h1>
          {unreadCount > 0 && (
            <p className="text-cream/40 text-sm mt-1">{unreadCount} unread</p>
          )}
        </div>
        {unreadCount > 0 && (
          <button
            onClick={markAllRead}
            className="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gold/25 text-gold/70 hover:border-gold hover:text-gold transition-colors text-sm"
          >
            <FiCheckCircle size={13} /> Mark all read
          </button>
        )}
      </div>

      {/* List */}
      {loading ? (
        <div className="space-y-3">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-24 shimmer rounded-2xl" />
          ))}
        </div>
      ) : notifs.length === 0 ? (
        <div className="text-center py-24">
          <FiBell size={40} className="mx-auto text-cream/15 mb-4" />
          <h3 className="font-display text-2xl text-cream/40 mb-2">
            All caught up!
          </h3>
          <p className="text-cream/25 text-sm">No notifications yet</p>
        </div>
      ) : (
        <div className="space-y-3">
          {notifs.map((n, i) => (
            <div
              key={n.id}
              className={`luxury-card rounded-2xl px-5 py-4 fade-up fade-up-${Math.min(i + 1, 4)} transition-all ${
                !n.read ? "border-gold/25" : "opacity-60"
              }`}
            >
              <div className="flex items-start gap-4">
                {/* Icon */}
                <div
                  className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 text-lg ${
                    !n.read ? "bg-gold/10" : "bg-white/5"
                  }`}
                >
                  {typeIcon(n.type)}
                </div>

                {/* Content */}
                <div className="flex-1 min-w-0">
                  <p
                    className={`text-sm leading-relaxed ${!n.read ? "text-cream/85" : "text-cream/45"}`}
                  >
                    {n.message}
                  </p>
                  <span className="text-xs text-cream/25 mt-1 block">
                    {n.created_at
                      ? format(new Date(n.created_at), "MMM d, yyyy · HH:mm")
                      : ""}
                  </span>

                  {/* ✅ أزرار Accept/Reject — تظهر فقط لـ booking_request */}
                  {n.type === "booking_request" && n.booking_id && (
                    <div className="flex gap-2 mt-3 flex-wrap">
                      {/* ── زر Chat مع الزبون ── */}
                      <button
                        onClick={async () => {
                            console.log(
                              "notification data:",
                              JSON.stringify(n),
                            );
                          try {
                            // ابدأ أو افتح المحادثة مع الزبون
                            const res = await chatService.createConversation({
                              other_user_id: n.booker_id, // ← نضيفه في الـ notification
                            });
                            const convId = res.data?.id || res.data?.data?.id;
                            console.log('conv response:', res.data)
                            navigate(`/chat?conversation_id=${convId}`);
                          } catch {
                            toast.error("Could not open chat");
                          }
                        }}
                        className="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-gold/25 text-gold/70 hover:border-gold hover:text-gold transition-colors text-xs"
                      >
                        <FiMessageCircle size={12} /> Chat with Guest
                      </button>

                      {/* ── Decline ── */}
                      {!n.read && (
                        <button
                          onClick={() => handleReject(n)}
                          disabled={!!actionLoad}
                          className="flex items-center gap-1.5 px-4 py-2 rounded-xl border border-red-500/30 text-red-400 hover:bg-red-500/10 transition-colors text-xs disabled:opacity-40"
                        >
                          {actionLoad === `reject_${n.id}` ? (
                            <span className="w-3 h-3 border border-red-400/50 border-t-red-400 rounded-full animate-spin" />
                          ) : (
                            <FiX size={12} />
                          )}
                          Decline
                        </button>
                      )}

                      {/* ── Accept ── */}
                      {!n.read && (
                        <button
                          onClick={() => handleAccept(n)}
                          disabled={!!actionLoad}
                          className="flex items-center gap-1.5 px-4 py-2 rounded-xl bg-green-500/15 border border-green-500/30 text-green-400 hover:bg-green-500/25 transition-colors text-xs disabled:opacity-40"
                        >
                          {actionLoad === `accept_${n.id}` ? (
                            <span className="w-3 h-3 border border-green-400/50 border-t-green-400 rounded-full animate-spin" />
                          ) : (
                            <FiCheck size={12} />
                          )}
                          Accept
                        </button>
                      )}
                    </div>
                  )}
                </div>

                {/* Unread dot */}
                {!n.read && (
                  <div className="w-2 h-2 rounded-full bg-gold shrink-0 mt-1.5" />
                )}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
