// src/admin/approval/ApproveRooms.jsx
import React, { useEffect, useMemo, useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import axios from "axios";
import AdminHeader from "../components/AdminHeader";
import { useApproval } from "./ApprovalContext";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Carousel,
  CarouselContent,
  CarouselItem,
  CarouselNext,
  CarouselPrevious,
} from "@/components/ui/carousel";
import { Input } from "@/components/ui/input";
import { Search, ChevronDown } from "lucide-react"; // Add ChevronDown import

import { NumberFormatter } from '../Function_Files/NumberFormatter';
const currency = (n) => NumberFormatter.formatCurrency(n);

export default function ApproveRooms() {
  const APIConn = `${localStorage.url}admin.php`;
  const { bookingId: bookingIdParam } = useParams();
  const navigate = useNavigate();
  const { state, setState } = useApproval();

  const [rooms, setRooms] = useState([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [checkIn, setCheckIn] = useState(state.checkIn || "");
  const [checkOut, setCheckOut] = useState(state.checkOut || "");
  const [fallbackRequestedTypeNames, setFallbackRequestedTypeNames] = useState(new Set());
  // Fallback per-type counts and total requested count when navigating without context
  const [fallbackRequestedTypeCounts, setFallbackRequestedTypeCounts] = useState({});
  const [fallbackRequestedRoomCount, setFallbackRequestedRoomCount] = useState(1);
  const [summary, setSummary] = useState({
    reference_no: '',
    customer_name: state.customerName || '',
    customer_email: '',
    customer_phone: '',
    total_amount: 0,
  });

  const bookingId = state.bookingId || Number(bookingIdParam);

  useEffect(() => {
    // Allow direct navigation via URL param; only redirect if neither is present
    if (!state.bookingId && !bookingIdParam) {
      navigate("/admin/online");
    }
    // Otherwise, proceed to fetch and render normally
  }, [state.bookingId, bookingIdParam, navigate]);

  // Normalize date strings (e.g., MM/DD/YYYY -> YYYY-MM-DD) for date inputs
  const normalizeDateStr = (str) => {
    if (!str) return "";
    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) return str; // already ISO
    const m = str.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (m) {
      const [_, mm, dd, yyyy] = m;
      const pad = (n) => String(n).padStart(2, '0');
      return `${yyyy}-${pad(mm)}-${pad(dd)}`;
    }
    const d = new Date(str);
    if (!isNaN(d)) return d.toISOString().slice(0, 10);
    return "";
  };

  useEffect(() => {
    const ni = normalizeDateStr(checkIn);
    const no = normalizeDateStr(checkOut);
    if (ni && ni !== checkIn) setCheckIn(ni);
    if (no && no !== checkOut) setCheckOut(no);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Fetch booking summary for Info Card
  useEffect(() => {
    const fetchSummary = async () => {
      try {
        const fd = new FormData();
        fd.append('method', 'viewBookingsEnhanced');
        const res = await axios.post(APIConn, fd);
        let rows = Array.isArray(res?.data) ? res.data : [];
        const match = rows.find(r => Number(r.booking_id) === Number(bookingId));
        if (match) {
          setSummary({
            reference_no: match.reference_no || '',
            customer_name: match.customer_name || state.customerName || '',
            customer_email: match.customer_email || '',
            customer_phone: match.customer_phone || '',
            total_amount: Number(match.total_amount ?? match.booking_totalAmount ?? 0),
          });
        }
      } catch (e) {
        console.error('Failed to fetch booking summary', e);
      }
    };
    if (bookingId) fetchSummary();
  }, [APIConn, bookingId, state.customerName]);

  const fetchAvailableRooms = async () => {
    setLoading(true);
    try {
      const fd = new FormData();
      // Use detailed room listing with room numbers and attached bookings
      fd.append("method", "viewAllRooms");
      const res = await axios.post(APIConn, fd);
      const data = Array.isArray(res.data) ? res.data : [];

      // Normalize the shape we rely on
      const mapped = data.map((r) => ({
        roomnumber_id: r.roomnumber_id ?? r.roomnumber_id,
        roomtype_id: r.roomtype_id ?? r.roomtype_id,
        roomtype_name: r.roomtype_name ?? r.roomtype_name,
        roomfloor: r.roomfloor ?? r.roomfloor,
        roomtype_description: r.roomtype_description ?? r.roomtype_description,
        roomtype_price: Number(r.roomtype_price ?? 0),
        roomtype_capacity: r.roomtype_capacity ?? r.roomtype_capacity,
        room_status_id: r.room_status_id ?? r.room_status_id,
        status_name: r.status_name ?? r.status_name,
        images: r.images ?? "",
        bookings: Array.isArray(r.bookings) ? r.bookings : (Array.isArray(r.room_bookings) ? r.room_bookings : []),
      }));

      setRooms(mapped);
    } catch (err) {
      console.error("Error fetching available rooms:", err);
      setRooms([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchAvailableRooms();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Filter: only requested types (if provided) and date-available; ignore status
  const requestedTypeNames = useMemo(
    () => new Set((state.requestedRoomTypes || []).map((t) => (t?.name || "").toLowerCase())),
    [state.requestedRoomTypes]
  );

  // If requested types are missing in state, fetch them from booking rooms
  useEffect(() => {
    const fetchRequestedTypes = async () => {
      if (!bookingId || requestedTypeNames.size) return;
      try {
        const fd = new FormData();
        fd.append('method', 'get_booking_rooms_by_booking');
        fd.append('json', JSON.stringify({ booking_id: Number(bookingId) }));
        const res = await axios.post(APIConn, fd);
        const rows = Array.isArray(res?.data) ? res.data : [];
        // Build a map: roomtype_id -> roomtype_name from available rooms
        const idToName = new Map();
        for (const r of rooms) {
          if (r.roomtype_id != null && r.roomtype_name) {
            idToName.set(Number(r.roomtype_id), String(r.roomtype_name));
          }
        }
        const names = new Set();
        const counts = {};
        for (const br of rows) {
          const rawName = br.roomtype_name || idToName.get(Number(br.roomtype_id)) || '';
          const normalized = String(rawName).trim().toLowerCase();
          if (normalized) {
            names.add(normalized);
            counts[normalized] = (counts[normalized] || 0) + 1;
          }
        }
        if (names.size) {
          setFallbackRequestedTypeNames(names);
          setFallbackRequestedTypeCounts(counts);
          const total = Object.values(counts).reduce((a, b) => a + (b || 0), 0);
          if (total > 0) setFallbackRequestedRoomCount(total);
        }
      } catch (e) {
        console.error('Failed to fetch requested types for booking', e);
      }
    };
    fetchRequestedTypes();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [bookingId, requestedTypeNames.size, APIConn, rooms.length]);

  // Availability helpers (require status Vacant for visibility and selection)
  const parseDate = (str) => (str ? new Date(str + "T00:00:00") : null);
  const rangesOverlap = (startA, endA, startB, endB) => startA < endB && endA > startB; // half-open
  const addDays = (date, days) => {
    const d = new Date(date);
    d.setDate(d.getDate() + days);
    return d;
  };
  const fmt = (date) => (date ? date.toISOString().slice(0, 10) : "");
  const tomorrow = fmt(addDays(new Date(), 1));
  const isAvailable = (room, startStr, endStr) => {
    const start = parseDate(startStr);
    const end = parseDate(endStr);
    // Only allow rooms explicitly marked as Vacant; block all other statuses
    const statusName = String(room.status_name || '').toLowerCase();
    if (statusName && statusName !== 'vacant') return false;
    // Hard block if legacy status indicates Occupied (defensive)
    if ((room.room_status_id && Number(room.room_status_id) === 1) ||
        statusName === 'occupied') {
      return false;
    }
    if (!start || !end) return true;
    const bookings = Array.isArray(room.bookings) ? room.bookings : [];
    for (const b of bookings) {
      const bStart = parseDate(b.checkin_date || b.booking_checkin_date || b.booking_checkin_dateandtime?.slice(0,10));
      const bEnd = parseDate(b.checkout_date || b.booking_checkout_date || b.booking_checkout_dateandtime?.slice(0,10));
      if (!bStart || !bEnd) continue;
      if (rangesOverlap(start, end, bStart, bEnd)) return false;
    }
    return true;
  };

  const finalList = useMemo(() => {
    const q = search.toLowerCase();
    // Determine the effective set of requested types (state first, fallback second)
    const effectiveRequested = requestedTypeNames.size ? requestedTypeNames : fallbackRequestedTypeNames;
    // Only show room types that were requested; if none, show none
    const filteredByType = rooms.filter((room) =>
      effectiveRequested.size
        ? effectiveRequested.has((room.roomtype_name || "").toLowerCase())
        : false
    );
    // Only show rooms that have status Vacant
    const filteredByStatus = filteredByType.filter((room) => String(room.status_name || '').toLowerCase() === 'vacant');
    return filteredByStatus
      .filter((room) =>
        room.roomtype_name?.toLowerCase().includes(q) ||
        room.roomtype_description?.toLowerCase().includes(q)
      )
      .filter((room) => isAvailable(room, checkIn, checkOut));
  }, [rooms, requestedTypeNames, fallbackRequestedTypeNames, search, checkIn, checkOut]);

  // Incremental loading: show 6 rooms initially, load more in chunks of 6
  const [visibleCount, setVisibleCount] = useState(6);
  useEffect(() => {
    // Reset pagination whenever filters change
    setVisibleCount(6);
  }, [search, checkIn, checkOut, requestedTypeNames.size]);
  const displayedList = useMemo(
    () => finalList.slice(0, Math.max(0, visibleCount)),
    [finalList, visibleCount]
  );

  // Availability counts by type for the selected dates
  const totalByType = useMemo(() => {
    const map = {};
    for (const r of rooms) {
      const key = (r.roomtype_name || '').toLowerCase();
      map[key] = (map[key] || 0) + 1;
    }
    return map;
  }, [rooms]);

  const availableByType = useMemo(() => {
    const map = {};
    for (const r of rooms) {
      const key = (r.roomtype_name || '').toLowerCase();
      const statusName = String(r.status_name || '').toLowerCase();
      // Count only Vacant rooms that are also available in the selected date range
      if (statusName === 'vacant' && isAvailable(r, checkIn, checkOut)) {
        map[key] = (map[key] || 0) + 1;
      } else if (!(key in map)) {
        map[key] = 0;
      }
    }
    return map;
  }, [rooms, checkIn, checkOut]);

  // Image helpers: robust fallbacks and URL handling
  const fallbackImagesForType = (typeName) => {
    const name = (typeName || '').toLowerCase();
    if (name.includes('single')) return ['single1.jpg', 'single2.jpg'];
    if (name.includes('double')) return ['double1.jpg', 'double2.jpg'];
    if (name.includes('twin')) return ['double1.jpg', 'double2.jpg'];
    if (name.includes('triple')) return ['triple1.jpg', 'triple2.jpg'];
    if (name.includes('family')) return ['familya1.jpg', 'familya2.jpg'];
    return ['demiren.jpg'];
  };

  const srcForImage = (img) => {
    if (!img) return `${localStorage.url}images/demiren.jpg`;
    const t = String(img).trim();
    if (/^https?:\/\//i.test(t)) return t;
    if (t.toLowerCase().startsWith('images/') || t.toLowerCase().startsWith('api/')) {
      return `${localStorage.url}${t}`;
    }
    return `${localStorage.url}images/${t}`;
  };

  // Date input handlers with validation
  const handleCheckInChange = (value) => {
    const inDate = parseDate(value);
    const outDate = parseDate(checkOut);
    // enforce earliest check-in as tomorrow
    if (value && value < tomorrow) {
      const t = parseDate(tomorrow);
      const next = addDays(t, 1);
      setCheckIn(tomorrow);
      if (!outDate || t >= outDate) setCheckOut(fmt(next));
      return;
    }
    if (inDate && outDate && inDate >= outDate) {
      const next = addDays(inDate, 1);
      setCheckIn(value);
      setCheckOut(fmt(next));
    } else {
      setCheckIn(value);
    }
  };

  const handleCheckOutChange = (value) => {
    const inDate = parseDate(checkIn);
    const outDate = parseDate(value);
    if (inDate && outDate && outDate <= inDate) {
      const next = addDays(inDate, 1);
      setCheckOut(fmt(next));
    } else {
      setCheckOut(value);
    }
  };

  // Selected rooms (limit: requestedRoomCount)
  const [selected, setSelected] = useState(state.selectedRooms || []);
  const computedTotals = useMemo(() => {
    const totals = [];
    const sCount = Number(state.requestedRoomCount);
    if (!Number.isNaN(sCount) && sCount > 0) totals.push(sCount);
    const fCount = Number(fallbackRequestedRoomCount);
    if (!Number.isNaN(fCount) && fCount > 0) totals.push(fCount);
    const sumTypes = Object.values(fallbackRequestedTypeCounts || {}).reduce((a, b) => a + Number(b || 0), 0);
    if (sumTypes > 0) totals.push(sumTypes);
    const nameLen = Array.isArray(state.requestedRoomTypes) ? state.requestedRoomTypes.length : 0;
    if (nameLen > 0) totals.push(nameLen);
    return totals;
  }, [state.requestedRoomCount, fallbackRequestedRoomCount, fallbackRequestedTypeCounts, state.requestedRoomTypes]);

  const maxSelect = Math.max(...(computedTotals.length ? computedTotals : [1]));
  const remaining = Math.max(0, maxSelect - selected.length);

  // Enforce per-type quotas when available
  const effectiveTypeCounts = useMemo(() => {
    // If state has requestedRoomTypes without counts, rely on fallback counts
    const obj = { ...fallbackRequestedTypeCounts };
    return obj;
  }, [fallbackRequestedTypeCounts]);

  const toggle = (room) => {
    // prevent selecting rooms that conflict with dates
    if (!isAvailable(room, checkIn, checkOut)) return;
    const exists = selected.some((r) => r.id === room.roomnumber_id);
    if (exists) {
      setSelected((prev) => prev.filter((r) => r.id !== room.roomnumber_id));
    } else {
      if (selected.length >= maxSelect) return; // enforce overall limit
      // Per-type limit: if counts known, restrict selection per roomtype
      const typeKey = String(room.roomtype_name || '').toLowerCase();
      const allowedForType = Number(effectiveTypeCounts[typeKey] || 0);
      if (allowedForType > 0) {
        const alreadyForType = selected.filter((r) => String(r.roomtype_name || '').toLowerCase() === typeKey).length;
        if (alreadyForType >= allowedForType) return;
      }
      setSelected((prev) => [
        ...prev,
        {
          id: room.roomnumber_id,
          roomtype_name: room.roomtype_name,
          price: Number(room.roomtype_price || 0),
        },
      ]);
    }
  };

  const proceed = () => {
    if (!checkIn || !checkOut) {
      alert("Please set both Check-In and Check-Out.");
      return;
    }
    if (selected.length !== maxSelect) {
      alert(`Please select exactly ${maxSelect} room(s).`);
      return;
    }
    
    // Set fixed times: 2:00 PM check-in, 12:00 PM check-out
    const checkInDateTime = new Date(checkIn);
    checkInDateTime.setHours(14, 0, 0, 0); // 2:00 PM
    
    const checkOutDateTime = new Date(checkOut);
    checkOutDateTime.setHours(12, 0, 0, 0); // 12:00 PM
    
    setState((prev) => ({ 
      ...prev, 
      selectedRooms: selected, 
      checkIn: checkInDateTime.toISOString().slice(0, 19).replace('T', ' '),
      checkOut: checkOutDateTime.toISOString().slice(0, 19).replace('T', ' ')
    }));
    navigate(`/admin/receipt/${bookingId}`);
  };

  // Floating scroll-to-bottom handler
  const scrollToBottom = () => {
    window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" });
  };

  // Display string for requested room types (with counts when available)
  const requestedDisplay = useMemo(() => {
    const parts = [];
    // Prefer types from navigation state
    if (Array.isArray(state.requestedRoomTypes) && state.requestedRoomTypes.length) {
      state.requestedRoomTypes.forEach((t) => {
        const name = t?.name || "";
        const key = name.toLowerCase();
        const count = Number((fallbackRequestedTypeCounts || {})[key] || t?.count || 0);
        if (name) parts.push(count > 0 ? `${name} x${count}` : name);
      });
    } else if (fallbackRequestedTypeCounts && Object.keys(fallbackRequestedTypeCounts).length) {
      // Fallback to counts hydrated from backend when state is missing
      Object.entries(fallbackRequestedTypeCounts).forEach(([key, count]) => {
        const proper = rooms.find((r) => String(r.roomtype_name || '').toLowerCase() === key)?.roomtype_name || key;
        parts.push(count > 0 ? `${proper} x${count}` : proper);
      });
    }
    return parts.join(', ') || '-';
  }, [state.requestedRoomTypes, fallbackRequestedTypeCounts, rooms]);

  return (
    <>
      <AdminHeader />
      <div className="lg:ml-72 p-6 max-w-6xl mx-auto relative">
        {/* Booking Info Card */}
        <Card className="mb-4">
          <CardHeader>
            <CardTitle>Booking Information</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
              <div>
                <div className="text-muted-foreground">Reference Number</div>
                <div className="font-medium text-foreground">{summary.reference_no || '-'}</div>
              </div>
              <div>
                <div className="text-muted-foreground">Fullname</div>
                <div className="font-medium text-foreground">{summary.customer_name || '-'}</div>
              </div>
              <div>
                <div className="text-muted-foreground">Roomtype Requested</div>
                <div className="font-medium text-foreground">{requestedDisplay}</div>
              </div>
              <div>
                <div className="text-muted-foreground">Total Payment</div>
                <div className="font-medium text-foreground">{currency(summary.total_amount)}</div>
              </div>
              <div>
                <div className="text-muted-foreground">Phone Number</div>
                <div className="font-medium text-foreground">{summary.customer_phone || '-'}</div>
              </div>
              <div>
                <div className="text-muted-foreground">Email</div>
                <div className="font-medium text-foreground">{summary.customer_email || '-'}</div>
              </div>
            </div>
          </CardContent>
        </Card>
        {/* Floating Button */}
        <button
          type="button"
          onClick={scrollToBottom}
          className="fixed bottom-8 right-8 z-50 bg-green-600 hover:bg-green-700 text-white rounded-full shadow-lg p-4 flex items-center justify-center transition"
          aria-label="Scroll to bottom"
        >
          <ChevronDown size={28} />
        </button>

        <h1 className="text-2xl font-bold mb-2 text-foreground">
          Approve Booking #{bookingId} — Step 1: Choose Available Rooms
        </h1>
        <p className="text-sm text-muted-foreground mb-6">
          Customer: <span className="font-medium">{state.customerName || "-"}</span> • Dates:{" "}
          <span className="font-medium">{state.checkIn}</span> →{" "}
          <span className="font-medium">{state.checkOut}</span> • Nights:{" "}
          <span className="font-medium">{state.nights}</span>
        </p>

        {/* Date controls (allow override) */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div>
            <label className="block text-sm font-medium text-foreground">Check-In</label>
            <input
              type="date"
              value={checkIn}
              disabled
              readOnly
              onChange={(e) => handleCheckInChange(e.target.value)}
              min={tomorrow}
              className="w-full border border-border rounded-lg px-3 py-2 bg-background text-foreground opacity-70 cursor-not-allowed"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-foreground">Check-Out</label>
            <input
              type="date"
              value={checkOut}
              disabled
              readOnly
              onChange={(e) => handleCheckOutChange(e.target.value)}
              min={checkIn ? fmt(addDays(parseDate(checkIn), 1)) : fmt(new Date())}
              className="w-full border border-border rounded-lg px-3 py-2 bg-background text-foreground opacity-70 cursor-not-allowed"
            />
          </div>
        </div>

        {/* Requested Summary */}
        <div className="mb-6 flex flex-wrap gap-2">
          <div className="bg-card border border-border rounded-lg px-3 py-2 text-sm text-foreground">
            Requested Rooms: <span className="font-semibold">{maxSelect}</span>
          </div>
          <div className="bg-card border border-border rounded-lg px-3 py-2 text-sm text-foreground">
            Remaining to select:{" "}
            <span className="font-semibold">{remaining}</span>
          </div>
          <div className="bg-card border border-border rounded-lg px-3 py-2 text-sm text-foreground">
            Types:{" "}
            {(state.requestedRoomTypes || []).map((t) => t.name).join(", ") || "-"}
          </div>
          {(state.requestedRoomTypes || []).length > 0 && (
            <div className="flex flex-wrap gap-2">
              {(state.requestedRoomTypes || []).map((t, i) => {
                const key = (t?.name || '').toLowerCase();
                const total = totalByType[key] || 0;
                const avail = availableByType[key] || 0;
                return (
                  <div
                    key={`${key}-${i}`}
                    className="bg-card border border-border rounded-lg px-3 py-2 text-sm text-foreground"
                  >
                    {t?.name || '-'}: <span className="font-semibold">{avail}</span> of {total} available
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Search */}
        <div className="relative mb-6">
          <Search className="absolute left-3 top-2.5 h-5 w-5 text-muted-foreground" />
          <Input
            placeholder="Search rooms..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-10"
          />
        </div>

        {loading ? (
          <p className="text-center text-muted-foreground">Loading rooms…</p>
        ) : finalList.length === 0 ? (
          <p className="text-center text-red-500 dark:text-red-400 font-semibold">No Available Rooms</p>
        ) : (
          <>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-6">
            {displayedList.map((room, i) => {
              const imgsRaw = room.images ? room.images.split(",").map((s) => s.trim()).filter(Boolean) : [];
              const imgs = imgsRaw.length ? imgsRaw : fallbackImagesForType(room.roomtype_name);
              const isPicked = selected.some((r) => r.id === room.roomnumber_id);
              const available = isAvailable(room, checkIn, checkOut);
              const typeKey = String(room.roomtype_name || '').toLowerCase();
              const allowedForType = Number((effectiveTypeCounts || {})[typeKey] || 0);
              const alreadyForType = selected.filter((r) => String(r.roomtype_name || '').toLowerCase() === typeKey).length;
              const violatesTypeQuota = !isPicked && allowedForType > 0 && alreadyForType >= allowedForType;
              const violatesTotalQuota = !isPicked && selected.length >= maxSelect;
              const isDisabled = !available || violatesTotalQuota || violatesTypeQuota;

              return (
                <div
                  key={`${room.roomnumber_id}-${i}`}
                  className={`border rounded-xl shadow-sm bg-card overflow-hidden ${
                    isPicked ? "ring-2 ring-green-500" : ""
                  }`}
                >
                  <Carousel className="relative">
                    <CarouselContent>
                      {imgs.length ? (
                        imgs.map((img, idx) => (
                          <CarouselItem key={idx}>
                            <img
                              src={srcForImage(img)}
                              alt={room.roomtype_name}
                              className="w-full h-56 object-cover"
                            />
                          </CarouselItem>
                        ))
                      ) : (
                        <CarouselItem>
                          <div className="w-full h-56 flex items-center justify-center bg-muted">
                            <span className="text-muted-foreground">No Image</span>
                          </div>
                        </CarouselItem>
                      )}
                    </CarouselContent>
                    <CarouselPrevious className="left-2" />
                    <CarouselNext className="right-2" />
                  </Carousel>

                  <div className="p-4">
                    <div className="flex items-center justify-between">
                      <h2 className="text-lg font-semibold text-foreground">
                        {room.roomtype_name} — Room #{room.roomnumber_id} (Floor {room.roomfloor})
                      </h2>
                      <span className="font-bold text-green-600 dark:text-green-400">
                        {currency(room.roomtype_price)}
                      </span>
                    </div>
                    <p className="text-sm text-muted-foreground mt-1">
                      {room.roomtype_description}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground">
                      Capacity: {room.roomtype_capacity}
                    </p>

                    {/* Availability indicator */}
                    {checkIn && checkOut && (
                      <div className="mt-2">
                        {available ? (
                          <span className="inline-block text-xs px-2 py-1 rounded bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300">Available</span>
                        ) : (
                          <span className="inline-block text-xs px-2 py-1 rounded bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300">Conflict on selected dates</span>
                        )}
                      </div>
                    )}

                    <button
                      onClick={() => toggle(room)}
                      className={`mt-3 px-4 py-2 rounded text-white ${
                        isPicked ? "bg-green-600 hover:bg-green-700" : isDisabled ? "bg-gray-400 cursor-not-allowed" : "bg-primary hover:bg-primary/90"
                      }`}
                      disabled={isDisabled}
                    >
                      {isPicked ? "Selected" : "Select Room"}
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
          {displayedList.length < finalList.length && (
            <div className="mt-6 flex justify-center">
              <button
                type="button"
                onClick={() => setVisibleCount((c) => c + 6)}
                className="px-6 py-2 rounded bg-primary text-white hover:bg-primary/90 transition-colors"
              >
                Show More Rooms
              </button>
            </div>
          )}
          </>
        )}

        <div className="mt-6 flex items-center justify-end gap-2">
          <button
            onClick={() => navigate("/admin/online")}
            className="px-4 py-2 rounded border border-border bg-card hover:bg-muted text-foreground transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={proceed}
            className="px-6 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition-colors"
            disabled={selected.length !== maxSelect}
          >
            Confirm Selection
          </button>
        </div>
      </div>
    </>
  );
}
