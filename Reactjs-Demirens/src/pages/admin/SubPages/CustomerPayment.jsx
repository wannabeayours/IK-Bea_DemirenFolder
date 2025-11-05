import React, { useEffect, useMemo, useRef, useState } from 'react'
import { NumberFormatter } from '../Function_Files/NumberFormatter'
import { DateFormatter } from '../Function_Files/DateFormatter'

import { cn } from "@/lib/utils"
import { Button } from "@/components/ui/button"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command"
import { ChevronsUpDownIcon, CheckIcon } from "lucide-react"
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger, SheetDescription } from "@/components/ui/sheet"
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/components/ui/card"
import {
  Table,
  TableBody,
  TableCaption,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import { Input } from "@/components/ui/input"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import { ScrollArea } from '@/components/ui/scroll-area'
import axios from 'axios'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog"

const CustomerPayment = ({ customer, onBack, paymentMethods = [] }) => {
  const APIConn = `${localStorage.url}admin.php`

  const [customerBills, setCustomerBills] = useState([]);
  const [selectedChargeId, setSelectedChargeId] = useState(null)
  const [selectedDiscountId, setSelectedDiscountId] = useState("")
  const [quantity, setQuantity] = useState(1);
  const [openCharge, setOpenCharge] = useState(false)
  const [openDiscount, setOpenDiscount] = useState(false)
  const [totalPrice, setTotalPrice] = useState(0);
  const [selectedRoomId, setSelectedRoomId] = useState(null);
  const [openRoom, setOpenRoom] = useState(false);
  const [customerRooms, setCustomerRooms] = useState([])
  const [chargeNotes, setChargeNotes] = useState("")
  const [notePrice, setNotePrice] = useState("")
  const [pendingNoteItems, setPendingNoteItems] = useState([])
  const [errors, setErrors] = useState({ chargeCategory: '', note: '', price: '', quantity: '', room: '' })
  const [showBackWarning, setShowBackWarning] = useState(false)
  const [backCountdown, setBackCountdown] = useState(3)
  const backTimerRef = useRef(null)

  // Confirm modal for Print Receipt
  const [isConfirmOpen, setIsConfirmOpen] = useState(false)
  const [confirmCountdown, setConfirmCountdown] = useState(3)
  const confirmTimerRef = useRef(null)

  useEffect(() => {
    return () => {
      if (backTimerRef.current) {
        clearInterval(backTimerRef.current)
      }
      if (confirmTimerRef.current) {
        clearInterval(confirmTimerRef.current)
      }
    }
  }, [])

  const [chargeOptions, setChargeOptions] = useState([])
  const [discountOptions, setDiscountOptions] = useState([])
  // Dedicated sheet open state for Add Charges
  const [isChargesSheetOpen, setChargesSheetOpen] = useState(false)
  // Save All modal state
  const [isSaveAllModalOpen, setIsSaveAllModalOpen] = useState(false)
  const [saveAllModalMessage, setSaveAllModalMessage] = useState("")
  const [canConfirmSaveAll, setCanConfirmSaveAll] = useState(false)
  const [chargesFilter, setChargesFilter] = useState('all')

  // Fetch charges and customer rooms when Add Charges sheet opens
  useEffect(() => {
    if (isChargesSheetOpen) {
      fetchChargeMastersWithCategories()
      getCustomerRooms()
    }
  }, [isChargesSheetOpen])

  // Fetch billing info on mount for billing_dateandtime
  useEffect(() => {
    if (customer?.booking_id) {
      getBillingInfo();
    }
  }, [])

  // If there is exactly one room, auto-select it
  useEffect(() => {
    if (Array.isArray(customerRooms) && customerRooms.length === 1) {
      const r = customerRooms[0]
      const id = r?.roomnumber_id ?? r?.room_id ?? r?.id
      if (id) setSelectedRoomId(Number(id))
    }
  }, [customerRooms])

  // New: payment form states
  const [amountReceived, setAmountReceived] = useState('')
  const [selectedPaymentMethodId, setSelectedPaymentMethodId] = useState(null)
  // New: track payment processing state to disable UI while request is in-flight
  const [isProcessingPayment, setIsProcessingPayment] = useState(false)
  // New: transactions endpoint for invoice-related operations
  const APIConnTransactions = `${localStorage.url}transactions.php`

  // Date helper for JSX (bind to class to avoid losing `this` context)
  const formatDate = (d, options) => DateFormatter.formatDate(d, options);
  // Billing date from tbl_billing
  const [billingDate, setBillingDate] = useState(null);

  // Modal controls handled via SheetTrigger and onOpenChange
  // Helper: robust type detection preferring Charge Master name, then category, then item name
  function detectTypeFromBill(bill) {
    const master = String(bill?.charge_master_name ?? bill?.charges_master_name ?? '').toLowerCase();
    const cat = String(bill?.charge_type ?? bill?.charges_category_name ?? '').toLowerCase();
    const name = String(bill?.item_name ?? '').toLowerCase();

    const hasDamage = (s) => s.includes('damage') || s.includes('broken') || s.includes('repair');
    const hasDrink = (s) => s.includes('drink') || s.includes('drinks') || s.includes('beverage') || s.includes('bar') || s.includes('soda') || s.includes('cola') || s.includes('wine') || s.includes('beer') || s.includes('juice') || s.includes('water');
    const hasFood = (s) => s.includes('food') || s.includes('restaurant') || s.includes('meal') || s.includes('dish');

    // Always detect Damage first
    if (hasDamage(master) || hasDamage(cat) || hasDamage(name)) return 'damage';

    // Priority: selected Charge Master overrides generic category names (e.g. "Food & Beverage")
    if (master) {
      if (hasDrink(master)) return 'drinks';
      if (hasFood(master)) return 'foods';
    }

    // Next: category (prefer Drinks over Foods if both words appear)
    if (cat) {
      if (hasDrink(cat)) return 'drinks';
      if (hasFood(cat)) return 'foods';
    }

    // Finally: item name keywords
    if (hasDrink(name)) return 'drinks';
    if (hasFood(name)) return 'foods';

    return null;
  }

  // Helper: display per-row charge type
  function getChargeTypeLabel(bill) {
    const t = detectTypeFromBill(bill);
    if (t === 'damage') return 'Charge Damage';
    if (t === 'drinks') return 'Drinks';
    if (t === 'foods') return 'Foods';
    return '-';
  }

  // Totals and balances
  const totalCharges = useMemo(() => {
    if (Array.isArray(customerBills) && customerBills.length > 0) {
      return customerBills.reduce((sum, bill) => {
        const price = parseFloat(bill.item_price) || 0;
        const qty = parseFloat(bill.item_amount) || 1;
        return sum + price * qty;
      }, 0);
    }
    const fallback = parseFloat(customer?.booking_totalAmount || customer?.billing_total_amount || 0) || 0;
    return fallback;
  }, [customerBills, customer]);

  // Category totals: Foods & Beverages vs Charge Damage
  const fnbTotal = useMemo(() => {
    const arr = Array.isArray(customerBills) ? customerBills : [];
    return arr.reduce((sum, bill) => {
      const cat = String(bill?.charge_type ?? bill?.charges_category_name ?? '').toLowerCase();
      const name = String(bill?.item_name ?? '').toLowerCase();
      const isFnb = cat.includes('food') || cat.includes('beverage') || cat.includes('restaurant') || name.includes('food') || name.includes('beverage');
      if (!isFnb) return sum;
      const price = parseFloat(bill?.item_price) || 0;
      const qty = parseFloat(bill?.item_amount) || 1;
      return sum + price * qty;
    }, 0);
  }, [customerBills]);

  const damageTotal = useMemo(() => {
    const arr = Array.isArray(customerBills) ? customerBills : [];
    return arr.reduce((sum, bill) => {
      const t = detectTypeFromBill(bill);
      if (t !== 'damage') return sum;
      const price = parseFloat(bill?.item_price) || 0;
      const qty = parseFloat(bill?.item_amount) || 1;
      return sum + price * qty;
    }, 0);
  }, [customerBills]);

  // Combined total for visual long-addition (Damage + F&B)
  const combinedFnbDamage = useMemo(() => fnbTotal + damageTotal, [fnbTotal, damageTotal]);

  // New: split Foods vs Drinks totals (skip aggregated summary rows to avoid double counting)
  const foodTotal = useMemo(() => {
    const arr = Array.isArray(customerBills) ? customerBills : [];
    return arr.reduce((sum, bill) => {
      const name = String(bill?.item_name ?? '').toLowerCase();
      if (name === 'food & beverage' || name === 'charge damage') return sum;
      const t = detectTypeFromBill(bill);
      if (t !== 'foods') return sum;
      const price = parseFloat(bill?.item_price) || 0;
      const qty = parseFloat(bill?.item_amount) || 1;
      return sum + price * qty;
    }, 0);
  }, [customerBills]);

  const drinksTotal = useMemo(() => {
    const arr = Array.isArray(customerBills) ? customerBills : [];
    return arr.reduce((sum, bill) => {
      const name = String(bill?.item_name ?? '').toLowerCase();
      if (name === 'food & beverage' || name === 'charge damage') return sum;
      const t = detectTypeFromBill(bill);
      if (t !== 'drinks') return sum;
      const price = parseFloat(bill?.item_price) || 0;
      const qty = parseFloat(bill?.item_amount) || 1;
      return sum + price * qty;
    }, 0);
  }, [customerBills]);

  const combinedFoodDrinksDamage = useMemo(() => foodTotal + drinksTotal + damageTotal, [foodTotal, drinksTotal, damageTotal]);

  

  const downpayment = useMemo(() => {
    const dp = parseFloat(
      customer?.booking_downpayment ??
      customer?.booking_payment ??
      customer?.billing_downpayment ??
      0
    ) || 0;
    return dp;
  }, [customer]);

  const balance = useMemo(() => {
    const bal = totalCharges - downpayment;
    return bal < 0 ? 0 : bal;
  }, [totalCharges, downpayment]);

  const amountParsed = useMemo(() => NumberFormatter.parseCurrencyInput(amountReceived), [amountReceived]);
  const filteredCustomerBills = useMemo(() => {
    if (!Array.isArray(customerBills)) return []
    if (chargesFilter === 'all') return customerBills
    const matchFn = (bill) => {
      const name = String(bill?.charge_type || bill?.charges_category_name || bill?.item_name || '').toLowerCase()
      if (chargesFilter === 'fnb') {
        return name.includes('food') || name.includes('beverage') || name.includes('restaurant')
      }
      if (chargesFilter === 'damages') {
        return name.includes('damage') || name.includes('broken') || name.includes('repair')
      }
      return true
    }
    return customerBills.filter(matchFn)
  }, [customerBills, chargesFilter])
  const changeAmount = useMemo(() => {
    const diff = amountParsed - balance;
    return diff > 0 ? diff : 0;
  }, [amountParsed, balance]);

  // Search, sort, and pagination for charges table
  const [searchQuery, setSearchQuery] = useState("");
  const [sortField, setSortField] = useState("name"); // 'name' | 'price'
  const [sortDirection, setSortDirection] = useState("asc"); // 'asc' | 'desc'
  const [page, setPage] = useState(1);
  const pageSize = 5;

  const processedBills = useMemo(() => {
    // Drop any invalid/placeholder rows that have no name and no price
    let arr = Array.isArray(filteredCustomerBills)
      ? filteredCustomerBills.filter((b) => {
          const name = String(b?.item_name ?? b?.charge_name ?? '').trim();
          const price = b?.item_price ?? b?.unit_price;
          // keep rows that have a name, or a numeric price > 0, or marked pending
          return !!name || (typeof price === 'number' && !Number.isNaN(price)) || !!b?.isPending;
        })
      : [];
    const q = searchQuery.trim().toLowerCase();
    if (q) {
      arr = arr.filter((b) => {
        const name = String(b?.item_name ?? b?.charge_name ?? "").toLowerCase();
        const category = String(b?.charge_type ?? b?.charges_category_name ?? b?.category ?? "").toLowerCase();
        return name.includes(q) || category.includes(q);
      });
    }
    const sorted = [...arr].sort((a, b) => {
      const aVal = sortField === "price" ? parseFloat(a?.item_price ?? a?.unit_price ?? 0) : String(a?.item_name ?? a?.charge_name ?? "").toLowerCase();
      const bVal = sortField === "price" ? parseFloat(b?.item_price ?? b?.unit_price ?? 0) : String(b?.item_name ?? b?.charge_name ?? "").toLowerCase();
      if (aVal < bVal) return sortDirection === "asc" ? -1 : 1;
      if (aVal > bVal) return sortDirection === "asc" ? 1 : -1;
      return 0;
    });
    return sorted;
  }, [filteredCustomerBills, searchQuery, sortField, sortDirection]);

  const totalPages = Math.max(1, Math.ceil((processedBills?.length ?? 0) / pageSize));
  const currentPage = Math.min(page, totalPages);
  const paginatedBills = useMemo(() => {
    const start = (currentPage - 1) * pageSize;
    const end = start + pageSize;
    return processedBills.slice(start, end);
  }, [processedBills, currentPage]);

  // Helper: get current active employee id from localStorage
  const getCurrentEmployeeId = () => {
    const keys = ['employee_id','employeeId','userId','user_id','userID','admin_id'];
    for (const k of keys) {
      const v = localStorage.getItem(k);
      if (v && parseInt(v)) return parseInt(v);
    }
    return null;
  };

  // Payment actions
  const handlePay = async () => {
    if (!customer?.booking_id) { alert('Missing booking_id'); return; }
    const payment_amount = NumberFormatter.parseCurrencyInput(amountReceived);
    if (!selectedPaymentMethodId) { alert('Select a payment method'); return; }
    setIsProcessingPayment(true);
    try {
      const employee_id = getCurrentEmployeeId();
      if (!employee_id) { alert('Missing employee_id'); setIsProcessingPayment(false); return; }

      const payload = {
        booking_id: customer.booking_id,
        employee_id,
        payment_method_id: selectedPaymentMethodId,
        invoice_status_id: 1,
        discount_id: selectedDiscountId || null,
        vat_rate: 0,
        downpayment: payment_amount,
        delivery_mode: 'pdf',
        doc_type: 'receipt',
        skip_checkout: true,
      };
      const formData = new FormData();
      formData.append('operation', 'createInvoice');
      formData.append('json', JSON.stringify(payload));
      const res = await axios.post(APIConnTransactions, formData);
      if (res.data?.success) {
        alert('Payment recorded. Invoice created.');
      } else {
        alert(res.data?.message || 'Failed to create invoice');
      }
    } catch (err) {
      console.error('handlePay error:', err);
      alert('Error processing payment');
    } finally {
      setIsProcessingPayment(false);
    }
  };

  const handlePrintReceipt = async () => {
    if (!customer?.booking_id) { alert('Missing booking_id'); return; }
    const baseUrl = localStorage.getItem('url') || '';

    // Ensure an invoice exists and stays in a non-checkout state before printing
    try {
      const employee_id = getCurrentEmployeeId() || 1;

      const payload = {
        booking_id: customer.booking_id,
        employee_id,
        payment_method_id: selectedPaymentMethodId || null,
        invoice_status_id: 1, // keep as created/not-checked-out
        discount_id: selectedDiscountId || null,
        vat_rate: 0,
        downpayment: 0,
        delivery_mode: 'pdf',
        doc_type: 'receipt',
        skip_checkout: true,
      };
      const formData = new FormData();
      formData.append('operation', 'createInvoice');
      formData.append('json', JSON.stringify(payload));
      await axios.post(APIConnTransactions, formData);
    } catch (e) {
      console.warn('Pre-print invoice creation failed (continuing to preview):', e);
    }

    // Open a preview of the receipt without hard navigation
    const params = new URLSearchParams({
      booking_id: String(customer.booking_id),
      delivery_mode: 'pdf',
      stream: '1',
      doc_type: 'receipt',
      preview: '1',
    });
    const url = baseUrl + 'generate-invoice.php?' + params.toString();
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  // Aggregate charges by category and submit one entry per category
  // Returns inserted aggregated charge IDs per category
  const aggregateAndSubmitCharges = async () => {
    try {
      const employee_id = getCurrentEmployeeId() || 1;
      if (!customer?.booking_id) { alert('Missing booking_id'); return false; }

      // Aggregate ONLY pending unsaved items to avoid double-counting already saved charges
      const allItems = (Array.isArray(pendingNoteItems) ? pendingNoteItems.map(p => ({
        item_name: p.note,
        item_price: p.price,
        item_amount: p.quantity,
        charge_type: p.category_name || p.charge_type || '',
      })) : []);

      const isDamage = (s) => {
        const x = String(s || '').toLowerCase();
        return x.includes('damage') || x.includes('broken') || x.includes('repair') || x.includes('charge damage');
      };
      const isFnb = (s) => {
        const x = String(s || '').toLowerCase();
        return (
          x.includes('food') ||
          x.includes('beverage') ||
          x.includes('restaurant') ||
          x.includes('drink') ||
          x.includes('drinks') ||
          x.includes('f&b')
        );
      };
      const isAdditional = (s) => {
        const x = String(s || '').toLowerCase();
        return x.includes('additional') || x.includes('service');
      };

      let damageTotal = 0;
      let fnbTotal = 0;
      let additionalTotal = 0;
      if (!allItems.length) {
        alert('No pending items to aggregate. Add items first.');
        return { damageChargeId: null, fnbChargeId: null, additionalChargeId: null };
      }
      for (const it of allItems) {
        const category = it?.charge_type || it?.charges_category_name || it?.category || '';
        const price = parseFloat(it?.item_price ?? it?.unit_price ?? 0) || 0;
        const qty = parseFloat(it?.item_amount ?? it?.quantity ?? 1) || 1;
        if (isDamage(category)) {
          damageTotal += price * qty;
        } else if (isFnb(category)) {
          fnbTotal += price * qty;
        } else if (isAdditional(category)) {
          additionalTotal += price * qty;
        }
      }

      // Try to find matching category ids from loaded chargeOptions
      const findCategoryId = (needle) => {
        const n = needle.toLowerCase();
        const m = (Array.isArray(chargeOptions) ? chargeOptions : []).find(c => String(c?.charges_category_name || '').toLowerCase().includes(n));
        return m?.charges_category_id || 4; // default to 4 (Additional Services)
      };
      const damageCategoryId = findCategoryId('damage');
      const fnbCategoryId = findCategoryId('food');
      const additionalCategoryId = findCategoryId('additional');

      const payloads = [];
      if (damageTotal > 0) {
        payloads.push({
          booking_id: customer.booking_id,
          charge_name: 'Charge Damage',
          charge_price: damageTotal,
          quantity: 1, // record once with total
          category_id: damageCategoryId,
          employee_id,
        });
      }
      if (fnbTotal > 0) {
        payloads.push({
          booking_id: customer.booking_id,
          charge_name: 'Food & Beverage',
          charge_price: fnbTotal,
          quantity: 1, // record once with total
          category_id: fnbCategoryId,
          employee_id,
        });
      }
      // Fallback: aggregate "Additional Services" when there are items not classified as F&B/Damage
      if (additionalTotal > 0 && payloads.length === 0) {
        payloads.push({
          booking_id: customer.booking_id,
          charge_name: 'Additional Services',
          charge_price: additionalTotal,
          quantity: 1,
          category_id: additionalCategoryId,
          employee_id,
        });
      }

      console.log('Aggregated submission payloads:', payloads);

      const insertedIds = { damageChargeId: null, fnbChargeId: null, additionalChargeId: null };
      let lastError = '';
      for (const pl of payloads) {
        const fd = new FormData();
        fd.append('operation', 'addBookingCharge');
        fd.append('json', JSON.stringify(pl));
        const res = await axios.post(APIConnTransactions, fd);
        if (!res.data?.success) {
          console.warn('Failed to add aggregated charge', pl, res.data);
          lastError = res?.data?.message || lastError || 'Unknown error adding charge';
        } else {
          const cid = res.data?.charge_id || null;
          if (pl.charge_name === 'Charge Damage') insertedIds.damageChargeId = cid;
          if (pl.charge_name === 'Food & Beverage') insertedIds.fnbChargeId = cid;
          if (pl.charge_name === 'Additional Services') insertedIds.additionalChargeId = cid;
        }
      }

      if (!insertedIds.damageChargeId && !insertedIds.fnbChargeId && !insertedIds.additionalChargeId && lastError) {
        alert(`Aggregated charges failed: ${lastError}`);
      }
      return insertedIds;
    } catch (e) {
      console.error('aggregateAndSubmitCharges error:', e);
      return { damageChargeId: null, fnbChargeId: null, additionalChargeId: null };
    }
  };

  // After inserting aggregated charges, record all item names as notes under them
  const saveNotesForAggregated = async (ids) => {
    try {
      const { damageChargeId, fnbChargeId, additionalChargeId } = ids || {};
      if (!damageChargeId && !fnbChargeId && !additionalChargeId) return;

      // Notes should reflect pending items that were aggregated
      const allItems = (Array.isArray(pendingNoteItems) ? pendingNoteItems.map(p => ({
        item_name: p.note,
        item_price: p.price,
        item_amount: p.quantity,
        charge_type: p.category_name || p.charge_type || '',
      })) : []);

      const isDamage = (s) => {
        const x = String(s || '').toLowerCase();
        return x.includes('damage') || x.includes('broken') || x.includes('repair') || x.includes('charge damage');
      };
      const isFnb = (s) => {
        const x = String(s || '').toLowerCase();
        return x.includes('food') || x.includes('beverage') || x.includes('restaurant') || x.includes('drink') || x.includes('drinks') || x.includes('f&b');
      };
      const isAdditional = (s) => {
        const x = String(s || '').toLowerCase();
        return x.includes('additional') || x.includes('service');
      };

      const damageItems = [];
      const fnbItems = [];
      const additionalItems = [];
      for (const it of allItems) {
        const category = it?.charge_type || it?.charges_category_name || it?.category || '';
        const name = String(it?.item_name || '').trim();
        if (!name) continue;
        // Skip aggregated rows themselves
        if (name === 'Food & Beverage' || name === 'Charge Damage' || name === 'Additional Services') continue;
        if (isDamage(category)) damageItems.push(name);
        else if (isFnb(category)) fnbItems.push(name);
        else if (isAdditional(category)) additionalItems.push(name);
      }

      // Insert notes referencing the single aggregated charges per category
      const postNote = async (booking_charges_id, note) => {
        const form = new FormData();
        form.append('operation', 'addBookingChargeNote');
        form.append('json', JSON.stringify({ booking_charges_id, booking_c_notes: note }));
        await axios.post(APIConnTransactions, form);
      };

      if (damageChargeId) {
        for (const n of damageItems) { await postNote(damageChargeId, n); }
      }
      if (fnbChargeId) {
        for (const n of fnbItems) { await postNote(fnbChargeId, n); }
      }
      if (additionalChargeId) {
        for (const n of additionalItems) { await postNote(additionalChargeId, n); }
      }
    } catch (err) {
      console.error('saveNotesForAggregated error:', err);
    }
  };

  const handleSaveAndPrintReceipt = async () => {
    // 1) Submit aggregated totals (single rows per category)
    const ids = await aggregateAndSubmitCharges();
    if (!ids?.damageChargeId && !ids?.fnbChargeId && !ids?.additionalChargeId) {
      alert('Failed to submit aggregated charges. Please review console output.');
    }
    // 2) Record all item names as notes under those single charges
    await saveNotesForAggregated(ids);
    // 3) Refresh billing, clear pending UI state
    await getBillingInfo();
    setPendingNoteItems([]);
    setChargesSheetOpen(false);
    // 4) Open receipt in a new tab without checkout status changes
    handlePrintReceipt();
  };

  // Confirmation modal flow
  const openConfirmModal = () => {
    setIsConfirmOpen(true)
    setConfirmCountdown(3)
    if (confirmTimerRef.current) clearInterval(confirmTimerRef.current)
    confirmTimerRef.current = setInterval(() => {
      setConfirmCountdown((prev) => {
        if (prev <= 1) {
          clearInterval(confirmTimerRef.current)
          confirmTimerRef.current = null
          setIsConfirmOpen(false)
          handleSaveAndPrintReceipt()
          return 0
        }
        return prev - 1
      })
    }, 1000)
  }

  const cancelConfirmModal = () => {
    if (confirmTimerRef.current) {
      clearInterval(confirmTimerRef.current)
      confirmTimerRef.current = null
    }
    setIsConfirmOpen(false)
  }

  const confirmNow = () => {
    if (confirmTimerRef.current) {
      clearInterval(confirmTimerRef.current)
      confirmTimerRef.current = null
    }
    setIsConfirmOpen(false)
    handleSaveAndPrintReceipt()
  }

  // API Connections
  const getBillingInfo = async () => {
    const billReqInfo = new FormData();
    billReqInfo.append('method', 'getCustomerBilling');
    billReqInfo.append('json', JSON.stringify({ booking_id: customer.booking_id }));

    try {
      const response = await axios.post(APIConn, billReqInfo);
      const data = response.data;

      console.log('Billing info:', data);

      if (Array.isArray(data)) {
        setCustomerBills(data);
        const bd = data[0]?.billing_dateandtime || null;
        if (bd) setBillingDate(bd);
      } else if (data?.success && Array.isArray(data?.billing_data)) {
        setCustomerBills(data.billing_data);
        const bd = data.billing_data[0]?.billing_dateandtime || null;
        if (bd) setBillingDate(bd);
      } else {
        console.warn('Unexpected billing data format:', data);
        setCustomerBills([]);
      }

    } catch (err) {
      console.error('Failed to fetch billing info:', err);
      setCustomerBills([]);
    }

  };

  const getCharges = async () => {
    const allowedIds = new Set([9, 10, 13]);

    // Helper to filter and set options
    const setFiltered = (rows, sourceTag = 'unknown') => {
      if (rows && Array.isArray(rows)) {
        const filtered = rows.filter((m) => allowedIds.has(Number(m.charges_master_id)));
        console.log(`[Charges] Loaded ${rows.length} from ${sourceTag}; using ${filtered.length} (IDs 9,10,13).`);
        setChargeOptions(filtered);
      } else {
        console.warn(`[Charges] Unexpected response from ${sourceTag}:`, rows);
        setChargeOptions([]);
      }
    };

    // First try: admin.php viewCharges
    const req1 = new FormData();
    req1.append('method', 'viewCharges');
    try {
      const res1 = await axios.post(APIConn, req1);
      if (res1?.data && Array.isArray(res1.data)) {
        const filtered1 = res1.data.filter((m) => allowedIds.has(Number(m.charges_master_id)));
        if (filtered1.length > 0) {
          setFiltered(res1.data, 'viewCharges');
          return;
        }
        console.warn('[Charges] viewCharges returned, but none matched IDs 9,10,13. Falling back.');
      } else {
        console.warn('[Charges] viewCharges unexpected response, falling back:', res1?.data);
      }
    } catch (err1) {
      console.error('[Charges] Error calling viewCharges, will fallback:', err1);
    }

    // Fallback: admin.php get_available_charges (amenity charges list)
    const req2 = new FormData();
    req2.append('method', 'get_available_charges');
    try {
      const res2 = await axios.post(APIConn, req2);
      setFiltered(res2?.data, 'get_available_charges');
    } catch (err2) {
      console.error('[Charges] Fallback get_available_charges also failed:', err2);
      setChargeOptions([]);
    }
  }

  // New: unified fetch that returns charge masters with their category fields
  const fetchChargeMastersWithCategories = async () => {
    const allowedIds = new Set([9, 10, 13]);
    const fmt = (rows) =>
      (Array.isArray(rows) ? rows : []).filter(r => allowedIds.has(Number(r?.charges_master_id)));

    // Primary: admin.php viewCharges (already joins category)
    try {
      const fd = new FormData();
      fd.append('method', 'viewCharges');
      const res = await axios.post(APIConn, fd);
      let rows = res?.data || [];
      const filtered = fmt(rows);
      if (filtered.length > 0) {
        setChargeOptions(filtered);
        return;
      }
      console.warn('[Charges] viewCharges returned no allowed IDs, trying get_available_charges.');
    } catch (err) {
      console.error('[Charges] viewCharges failed, trying get_available_charges:', err);
    }

    // Fallback: amenity listing which also includes category fields
    try {
      const fd2 = new FormData();
      fd2.append('method', 'get_available_charges');
      const res2 = await axios.post(APIConn, fd2);
      const filtered2 = fmt(res2?.data || []);
      setChargeOptions(filtered2);
    } catch (err2) {
      console.error('[Charges] get_available_charges failed:', err2);
      setChargeOptions([]);
    }
  }

  const getDiscounts = async () => {
    const discountReq = new FormData()
    // Use viewDiscounts per admin.php
    discountReq.append('method', 'viewDiscounts')

    try {
      const res = await axios.post(APIConn, discountReq)
      if (res.data && Array.isArray(res.data)) {
        setDiscountOptions(res.data)
      } else {
        console.warn("Unexpected response for discounts:", res.data)
        setDiscountOptions([])
      }
    } catch (err) {
      console.error("Error fetching discounts:", err)
      setDiscountOptions([])
    }
  }

  const getCustomerRooms = async () => {
    // Prefer booking-specific rooms so charges can be tied correctly
    const req = new FormData();
    req.append('method', 'get_booking_rooms_by_booking');
    req.append('json', JSON.stringify({ booking_id: customer.booking_id }));

    try {
      const response = await axios.post(APIConn, req);
      const data = response.data;
      if (Array.isArray(data)) {
        setCustomerRooms(data);
      } else if (data && Array.isArray(data?.rooms)) {
        // Some endpoints return { rooms: [...] }
        setCustomerRooms(data.rooms);
      } else {
        console.warn('Unexpected rooms response:', data);
        // Fallback: fetch all rooms if booking-specific fails
        try {
          const fallbackReq = new FormData();
          fallbackReq.append('method', 'viewAllRooms');
          const fallbackRes = await axios.post(APIConn, fallbackReq);
          setCustomerRooms(Array.isArray(fallbackRes.data) ? fallbackRes.data : []);
        } catch (fallbackErr) {
          console.error('Fallback rooms fetch failed:', fallbackErr);
          setCustomerRooms([]);
        }
      }
    } catch (err) {
      console.error('Error fetching customer rooms:', err);
      setCustomerRooms([]);
    }
  };

  const addCharges = async () => {
    const selectedCharge = chargeOptions.find(c => c.charges_master_id === selectedChargeId);
    const selectedDiscount = discountOptions.find(d => d.discounts_id === selectedDiscountId);
    const selectedRoom = null;

    const chargePayload = {
      booking_id: customer.booking_id,
      charge: {
        id: selectedChargeId,
        name: selectedCharge?.charges_master_name || null,
        // If manual note price is provided, use it; otherwise fallback to master price
        amount: notePrice ? Number(notePrice) : (selectedCharge?.charges_master_price || 0),
      },
      discount: selectedDiscountId
        ? {
            id: selectedDiscountId,
            name: selectedDiscount?.discounts_name || null,
            percent: selectedDiscount?.discounts_percentage || 0,
          }
        : null,
      room: {
        id: null,
        number: null,
      },
      quantity: quantity,
      // Compute total from manual price when present; otherwise use existing computed total
      total_price: notePrice ? (Number(notePrice) * Number(quantity)) : totalPrice,
      notes: chargeNotes || null,
    };

    console.log("🧾 Charge Payload to Submit:", chargePayload);

    const formData = new FormData();
    formData.append('method', 'addCustomerCharges');
    formData.append('json', JSON.stringify(chargePayload));

    try {
      const response = await axios.post(APIConn, formData);
      console.log("✅ Server response:", response.data);
    } catch (error) {
      console.error("❌ Error submitting charge:", error);
    }
  };

  const addPendingItem = () => {
    const newErrors = { chargeCategory: '', note: '', price: '', quantity: '', room: '' }
    const priceNum = Number(notePrice)
    if (selectedChargeId == null) newErrors.chargeCategory = 'This input is missing'
    if (!chargeNotes?.trim()) newErrors.note = 'This input is missing'
    if (!priceNum || priceNum <= 0) newErrors.price = 'This input is missing'
    const qtyNum = Number(quantity) || 1
    if (!qtyNum || qtyNum < 1) newErrors.quantity = 'This input is missing'
    if (Array.isArray(customerRooms) && customerRooms.length > 1 && (selectedRoomId == null)) newErrors.room = 'This input is missing'

    // If any errors, show messages below inputs
    if (newErrors.chargeCategory || newErrors.note || newErrors.price || newErrors.quantity || newErrors.room) {
      setErrors(newErrors)
      return
    }
    setErrors({ chargeCategory: '', note: '', price: '', quantity: '', room: '' })
    const selectedCat = chargeOptions.find(c => Number(c.charges_master_id) === Number(selectedChargeId))
    // Resolve room selection and corresponding booking_room_id
    let resolvedRoomId = selectedRoomId
    if (!resolvedRoomId && Array.isArray(customerRooms) && customerRooms.length === 1) {
      const r = customerRooms[0]
      resolvedRoomId = r?.roomnumber_id ?? r?.room_id ?? r?.id ?? null
    }
    const selectedRoomObj = Array.isArray(customerRooms)
      ? customerRooms.find(r => String(r?.roomnumber_id ?? r?.room_id ?? r?.id) === String(resolvedRoomId))
      : null
    const bookingRoomId = selectedRoomObj?.booking_room_id ?? selectedRoomObj?.bookingRoom_id ?? null

    const tempId = `${Date.now()}_${Math.random().toString(36).slice(2)}`
    const item = {
      note: chargeNotes.trim(),
      price: priceNum,
      quantity: qtyNum,
      category_id: selectedCat ? Number(selectedCat.charges_category_id) : null,
      category_name: selectedCat?.charges_category_name || null,
      master_name: selectedCat?.charges_master_name || null,
      charges_master_id: selectedCat ? Number(selectedCat.charges_master_id) : null,
      booking_room_id: bookingRoomId,
      roomnumber_id: resolvedRoomId || '-',
      temp_id: tempId,
    }
    setPendingNoteItems(prev => [...prev, item])
    // Also reflect immediately in the table
    setCustomerBills(prev => [
      ...prev,
      {
        roomnumber_id: resolvedRoomId || '-',
        item_name: item.note,
        item_amount: item.quantity,
        item_price: item.price,
        charge_type: item.category_name || '-',
        charge_master_name: item.master_name || null,
        isPending: true,
        temp_id: tempId,
      }
    ])
    // reset inputs for next item
    setChargeNotes("")
    setNotePrice("")
    setQuantity(1)
  }

  const removePendingItem = (tempId) => {
    if (!tempId) return;
    setPendingNoteItems(prev => prev.filter(p => p.temp_id !== tempId))
    setCustomerBills(prev => prev.filter(b => b.temp_id !== tempId))
  }

  const saveAllPendingItems = async () => {
    if (!customer?.booking_id) { alert('Missing booking_id'); return; }
    if (!pendingNoteItems.length) { alert('No items to save'); return; }

    try {
      // Aggregate pending items by booking_room_id and charges_master_id
      // For each room, submit a single row per charge type (master) with totals aggregated
      const roomGroups = new Map();
      for (const item of pendingNoteItems) {
        const brid = Number(item.booking_room_id) || 0;
        const cmid = Number(item.charges_master_id) || 0;
        const qty = Number(item.quantity) || 1;
        const price = Number(item.price) || 0;
        const note = (item.note || '').trim();
        if (!brid || !cmid || qty <= 0) continue;
        if (!roomGroups.has(brid)) roomGroups.set(brid, new Map());
        const byMaster = roomGroups.get(brid);
        if (!byMaster.has(cmid)) {
          byMaster.set(cmid, { sumQty: 0, sumTotal: 0, notes: [] });
        }
        const grp = byMaster.get(cmid);
        grp.sumQty += qty;
        grp.sumTotal += (price * qty);
        if (note) grp.notes.push(note);
      }

      // Submit aggregated amenities per room
      for (const [booking_room_id, byMaster] of roomGroups.entries()) {
        const amenities = [];
        for (const [cmid, agg] of byMaster.entries()) {
          const amenity = {
            charges_master_id: cmid,
            // Per requirement: set price and total to the aggregated sum of prices
            booking_charges_price: agg.sumTotal,
            booking_charges_quantity: agg.sumQty,
            booking_charges_total: agg.sumTotal,
            // Include all notes to be persisted by backend for this aggregated charge
            notes: agg.notes,
          };
          amenities.push(amenity);
        }

        if (amenities.length === 0) continue;

        const fd = new FormData();
        fd.append('method', 'add_amenity_request');
        fd.append('json', JSON.stringify({
          booking_room_id,
          amenities,
          booking_charge_status: 2, // mark as approved/delivered
        }));
        await axios.post(APIConn, fd);
      }
      // Refresh official billing info from server
      await getBillingInfo()
      // Clear pending
      setPendingNoteItems([])
      setChargesSheetOpen(false)
    } catch (err) {
      console.error('Batch save failed:', err)
      alert('Failed saving charges. Please try again.')
    }
  }

  // Back button guard: warn and delay leaving if there are pending items
  const handleBackClick = () => {
    if (pendingNoteItems.length > 0) {
      setShowBackWarning(true)
      setBackCountdown(3)
      if (backTimerRef.current) {
        clearInterval(backTimerRef.current)
      }
      backTimerRef.current = setInterval(() => {
        setBackCountdown(prev => {
          if (prev <= 1) {
            clearInterval(backTimerRef.current)
            onBack()
            return 0
          }
          return prev - 1
        })
      }, 1000)
    } else {
      onBack()
    }
  }

  useEffect(() => {
    const selectedCharge = chargeOptions.find(
      c => c.charges_master_id == selectedChargeId
    );
    // Prefer manual note price when provided
    const chargePrice = notePrice ? Number(notePrice) : 0;

    const selectedDiscount = discountOptions.find(
      d => d.discounts_id == selectedDiscountId
    );
    // Apply discount only when a predefined charge is selected
    const discountPercent = selectedChargeId ? (selectedDiscount?.discounts_percentage || 0) : 0;

    const totalBeforeDiscount = chargePrice * quantity;
    const discountedAmount = totalBeforeDiscount * (discountPercent / 100);
    const calculatedTotal = totalBeforeDiscount - discountedAmount;

    setTotalPrice(calculatedTotal);
  }, [selectedChargeId, selectedDiscountId, quantity, chargeOptions, discountOptions, notePrice]);

  return (
    <>
      <div id='MainPage'>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          {/* Row 1: Basic Info spans 2 columns */}
          <div className="space-y-6 md:col-span-2">

            {/* Basic Customer Info */}
            <Card>
              <CardHeader>
                <CardTitle>Basic Customer Info</CardTitle>
              </CardHeader>
              <CardContent className="space-y-2 text-sm">
                <p>
                  <span className="font-semibold">Customer Name:</span>{" "}
                  {customer?.fullname || customer?.customers_online_username || customer?.customer_name || customer?.customers_fullname || "-"}
                </p>
                <p>
                  <span className="font-semibold">Invoice No:</span>{" "}
                  {customer?.reference_no || "-"}
                </p>
                <p>
                  <span className="font-semibold">Billing date:</span>{" "}
                  {billingDate ? formatDate(billingDate) : (customer?.booking_created_at ? formatDate(customer.booking_created_at) : "-")}
                </p>
              </CardContent>
            </Card>

            {/* Removed duplicate Charges header and button to align rows */}
          </div>

          {/* Row 1: Payment in column 3 */}
          <div className="h-fit md:col-span-1">
            <Card className="h-full">
              <CardHeader>
                <CardTitle>Payment</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4 text-sm">
                <div className="space-y-2">
                  <Label htmlFor="amountReceived">Amount Received</Label>
                  <Input id="amountReceived" placeholder="₱ 0.00" value={amountReceived} onChange={(e) => setAmountReceived(e.target.value)} />
                </div>
                <p><span className="font-semibold">Change:</span> {NumberFormatter.formatCurrency(changeAmount)}</p>

                {/* Dynamic Payment Methods */}
                {Array.isArray(paymentMethods) && paymentMethods.length > 0 ? (
                  <div className="space-y-2">
                    <Label htmlFor="payment_method">Payment Method</Label>
                    <Select
                      value={selectedPaymentMethodId ? selectedPaymentMethodId.toString() : ""}
                      onValueChange={(value) => setSelectedPaymentMethodId(value ? parseInt(value) : null)}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select payment method" />
                      </SelectTrigger>
                      <SelectContent>
                        {paymentMethods.map(pm => (
                          <SelectItem key={pm.payment_method_id} value={pm.payment_method_id.toString()}>
                            {pm.payment_method_name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                ) : (
                  <div className="text-xs text-muted-foreground">No payment methods available.</div>
                )}
              </CardContent>
              <CardFooter className="flex justify-end gap-4">
                <Button
                  className="bg-green-600 text-white hover:bg-green-700"
                  onClick={handlePay}
                  disabled={!selectedPaymentMethodId || !amountReceived || isProcessingPayment}
                >
                  Pay
                </Button>
                <Button
                  className="bg-blue-600 text-white hover:bg-blue-700"
                  onClick={openConfirmModal}
                  disabled
                >
                  Print Receipt
                </Button>
              </CardFooter>

            </Card>
          </div>
          {/* Row 2: Charges List spans two columns */}
          <div className="md:col-span-2">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>
                  Charges List
                </CardTitle>
                <div className="flex items-center gap-3">
                  <Select value={chargesFilter} onValueChange={setChargesFilter}>
                    <SelectTrigger className="w-[220px]">
                      <SelectValue placeholder="Filter charges" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Show All</SelectItem>
                      <SelectItem value="fnb">Show Foods & Beverages</SelectItem>
                      <SelectItem value="damages">Show Damages</SelectItem>
                    </SelectContent>
                  </Select>
                  <Button
                    className="bg-emerald-600 text-white hover:bg-emerald-700"
                    onClick={() => {
                      const isEmpty = (Array.isArray(paginatedBills) ? paginatedBills.length === 0 : true);
                      if (isEmpty) {
                        setSaveAllModalMessage("Empty List, please fill out the list with charges")
                        setCanConfirmSaveAll(false)
                      } else {
                        setSaveAllModalMessage("Are you sure you want to save all charges listed?")
                        setCanConfirmSaveAll(true)
                      }
                      setIsSaveAllModalOpen(true)
                    }}
                  >
                    Save All
                  </Button>
                <Sheet
                  open={isChargesSheetOpen}
                  onOpenChange={(v) => {
                    // Keep Sheet purely controlled; effects handle data fetching
                    setChargesSheetOpen(v)
                  }}
                >
                  <SheetTrigger asChild>
                    <Button className="bg-black text-white hover:bg-gray-800">
                      Add Charges
                    </Button>
                  </SheetTrigger>
                  <SheetContent side="right" forceMount onOpenAutoFocus={(e) => e.preventDefault()}>
                    <SheetHeader>
                      <SheetTitle>Add Charges</SheetTitle>
                      <SheetDescription>
                        Add Foods & Beverages or Damage items with notes and amounts.
                      </SheetDescription>
                    </SheetHeader>
                    <div className="space-y-6 px-2 md:px-6">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Charges Dropdown (Charge Master Select) */}
                        <div>
                          <label className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Charge Master
                          </label>
                          <select
                            className="w-full border rounded-md px-3 py-2 text-gray-800"
                            value={selectedChargeId != null ? String(selectedChargeId) : ""}
                            onChange={(e) => {
                              const v = e.target.value
                              setSelectedChargeId(v ? Number(v) : null)
                            }}
                          >
                            <option value="">Select charge...</option>
                            {chargeOptions.map((charge) => (
                              <option key={charge.charges_master_id} value={String(charge.charges_master_id)}>
                                {charge.charges_master_name}
                              </option>
                            ))}
                          </select>
                          {errors.chargeCategory && (
                            <p className="text-red-600 text-xs mt-1">{errors.chargeCategory}</p>
                          )}
                        </div>
                        {/* Discount Charges removed by request */}
                      </div>

                      {/* Room selection: show dropdown only if customer has multiple rooms */}
                      {Array.isArray(customerRooms) && customerRooms.length > 1 && (
                        <div>
                          <label className="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Room
                          </label>
                          <select
                            className="w-full border rounded-md px-3 py-2 text-gray-800"
                            value={selectedRoomId != null ? String(selectedRoomId) : ""}
                            onChange={(e) => {
                              const v = e.target.value
                              setSelectedRoomId(v ? Number(v) : null)
                            }}
                          >
                            <option value="">Select room...</option>
                            {customerRooms.map((room) => {
                              const id = room?.roomnumber_id ?? room?.room_id ?? room?.id
                              const label = room?.room_number ?? room?.roomnumber_number ?? room?.roomnumber_name ?? String(id)
                              return (
                                <option key={String(id)} value={String(id)}>
                                  {label}
                                </option>
                              )
                            })}
                          </select>
                          {errors.room && (
                            <p className="text-red-600 text-xs mt-1">{errors.room}</p>
                          )}
                        </div>
                      )}

                      <div className="space-y-4">
                        {/* Notes Textarea (first) */}
                        <div>
                          <label className="block text-sm font-semibold text-gray-700 mb-1">Notes</label>
                          <textarea
                            value={chargeNotes}
                            onChange={(e) => setChargeNotes(e.target.value)}
                            placeholder="Describe the item (e.g., Food & Beverage or Damage details)"
                            className="w-full border rounded-md px-3 py-2 text-gray-800 min-h-[80px]"
                          />
                          {errors.note && (
                            <p className="text-red-600 text-xs mt-1">{errors.note}</p>
                          )}
                        </div>

                        {/* Manual Price input (below textarea) */}
                        <div>
                          <label className="block text-sm font-semibold text-gray-700 mb-1">Price</label>
                          <input
                            type="text"
                            inputMode="decimal"
                            value={notePrice}
                            onChange={(e) => {
                              // allow only digits and a single optional decimal point
                              let v = e.target.value.replace(',', '.');
                              if (/^\d*\.?\d*$/.test(v)) {
                                setNotePrice(v);
                              }
                            }}
                            placeholder="Enter price"
                            className="w-full border rounded-md px-3 py-2 text-gray-800"
                          />
                          {errors.price && (
                            <p className="text-red-600 text-xs mt-1">{errors.price}</p>
                          )}
                        </div>

                        {/* Quantity Input */}
                        <div>
                          <label className="block text-sm font-semibold text-gray-700 mb-1">Quantity</label>
                          <input
                            type="text"
                            inputMode="numeric"
                            pattern="[0-9]*"
                            value={String(quantity)}
                            onChange={(e) => {
                              const v = e.target.value;
                              if (/^\d*$/.test(v)) {
                                setQuantity(v === '' ? 0 : Number(v));
                              }
                            }}
                            placeholder="Enter Quantity"
                            className="w-full border rounded-md px-3 py-2 text-gray-800"
                          />
                          {errors.quantity && (
                            <p className="text-red-600 text-xs mt-1">{errors.quantity}</p>
                          )}
                        </div>

                        {/* Add item to pending list */}
                        <div className="flex justify-end">
                          <Button className="bg-gray-900 text-white hover:bg-gray-700" onClick={addPendingItem}>
                            Add Item
                          </Button>
                        </div>
                      </div>
                    </div>
                  </SheetContent>
                </Sheet>
                </div>
              </CardHeader>
              <CardContent className="pt-6">
                {/* Search and sort controls */}
                <div className="flex flex-col md:flex-row items-start md:items-center gap-2 mb-2">
                  <Input
                    placeholder="Search charges or category"
                    value={searchQuery}
                    onChange={(e) => { setSearchQuery(e.target.value); setPage(1); }}
                    className="md:w-1/2"
                  />
                  <div className="flex items-center gap-2">
                    <Select value={sortField} onValueChange={(v) => setSortField(v)}>
                      <SelectTrigger className="w-40">
                        <SelectValue placeholder="Sort by" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="name">Charge Name</SelectItem>
                        <SelectItem value="price">Price</SelectItem>
                      </SelectContent>
                    </Select>
                    <Button variant="outline" onClick={() => setSortDirection(d => d === 'asc' ? 'desc' : 'asc')}>
                      {sortDirection === 'asc' ? 'Asc' : 'Desc'}
                    </Button>
                  </div>
                </div>
                <ScrollArea className="w-full max-h-[300px] overflow-auto">
                  <div className="min-w-[700px]"> {/* Ensures horizontal scroll if needed */}
                    <Table>
                      <TableCaption>Breakdown of customer charges</TableCaption>
                      <TableHeader>
                        <TableRow>
                          <TableHead className="w-[80px]">Room No.</TableHead>
                          <TableHead>Charge Name</TableHead>
                          <TableHead>Charge Type</TableHead>
                          <TableHead>Quantity</TableHead>
                          <TableHead>Unit Price</TableHead>
                          <TableHead className="text-right">Total</TableHead>
                          <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {paginatedBills.length > 0 ? (
                          paginatedBills.map((bill, index) => (
                            <TableRow key={index}>
                              <TableCell className="font-medium">{bill.roomnumber_id}</TableCell>
                              <TableCell>{bill.item_name}</TableCell>
                              <TableCell>{getChargeTypeLabel(bill)}</TableCell>
                              <TableCell>{(bill.item_amount ?? 1)}</TableCell>
                              <TableCell>
                                {NumberFormatter.formatCurrency(bill.item_price)}
                              </TableCell>
                              <TableCell className="text-right">
                                {NumberFormatter.formatCurrency(parseFloat(bill.item_price) * (bill.item_amount || 1))}
                              </TableCell>
                              <TableCell className="text-right">
                                {bill.isPending && bill.temp_id ? (
                                  <Button variant="outline" size="sm" onClick={() => removePendingItem(bill.temp_id)}>
                                    Remove
                                  </Button>
                                ) : null}
                              </TableCell>
                            </TableRow>
                          ))
                        ) : (
                          <TableRow>
                            <TableCell colSpan={7} className="text-center text-muted-foreground py-4">
                              No charges found for this customer.
                            </TableCell>
                          </TableRow>
                        )}
                      </TableBody>
                    </Table>
                  </div>
                </ScrollArea>
                {/* Pagination controls */}
                <div className="flex items-center justify-between mt-2">
                  <div className="text-xs text-muted-foreground">Page {currentPage} of {totalPages}</div>
                  <div className="flex items-center gap-2">
                    <Button variant="outline" disabled={currentPage <= 1} onClick={() => setPage(p => Math.max(1, p - 1))}>Prev</Button>
                    <Button variant="outline" disabled={currentPage >= totalPages} onClick={() => setPage(p => Math.min(totalPages, p + 1))}>Next</Button>
                  </div>
                </div>
            </CardContent>
          </Card>

          {/* Save All Confirmation / Empty List Modal */}
          <Dialog open={isSaveAllModalOpen} onOpenChange={(open) => setIsSaveAllModalOpen(open)}>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Save All Charges</DialogTitle>
                <DialogDescription>
                  {saveAllModalMessage}
                </DialogDescription>
              </DialogHeader>
              <DialogFooter className="flex gap-2">
                <Button variant="outline" onClick={() => setIsSaveAllModalOpen(false)}>Close</Button>
                {canConfirmSaveAll && (
                  <Button className="bg-emerald-600 text-white hover:bg-emerald-700" onClick={async () => {
                    setIsSaveAllModalOpen(false)
                    await saveAllPendingItems()
                    alert('All charges saved successfully.')
                  }}>
                    Confirm Save
                  </Button>
                )}
              </DialogFooter>
            </DialogContent>
          </Dialog>
          </div>
          {/* Row 2: Total Amount in column 3 */}
          <div className="md:col-span-1">
            <Card>
              <CardHeader>
                <CardTitle>Total Amount</CardTitle>
              </CardHeader>
              <CardContent className="text-sm space-y-2">
                {/* Long addition style */}
                <div className="space-y-1">
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Foods Total</span>
                    <span className="font-semibold">{NumberFormatter.formatCurrency(foodTotal)}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Drinks Total</span>
                    <span className="font-semibold">{NumberFormatter.formatCurrency(drinksTotal)}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-muted-foreground">Damages Total</span>
                    <span className="font-semibold">{NumberFormatter.formatCurrency(damageTotal)}</span>
                  </div>
                  <div className="flex items-center justify-between border-t pt-1">
                    <span className="font-medium">= Total Amount</span>
                    <span className="font-bold">{NumberFormatter.formatCurrency(combinedFoodDrinksDamage)}</span>
                  </div>
                </div>

                {/* Existing overall totals */}
                <div className="pt-2 space-y-1">
                  <p>
                    <span className="font-semibold">Overall Total:</span>{" "}
                    {NumberFormatter.formatCurrency(totalCharges)}
                  </p>
                  <p>
                    <span className="font-semibold">Downpayment:</span>{" "}
                    {NumberFormatter.formatCurrency(downpayment)}
                  </p>
                  <p>
                    <span className="font-semibold">Balance:</span>{" "}
                    {NumberFormatter.formatCurrency(balance)}
                  </p>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>

        <div className="mt-4 flex items-center gap-2">
          <Button onClick={handleBackClick} variant="outline">
            ← Back
          </Button>
        </div>
        {showBackWarning && (
          <p className="mt-2 text-sm text-yellow-700">
            Are you sure to leave this page? There are pending items. Leaving in {backCountdown}s
          </p>
        )}
        
        {/* Confirmation Modal for Print Receipt */}
        <Dialog open={isConfirmOpen} onOpenChange={(open) => {
          if (!open) {
            cancelConfirmModal()
          }
        }}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Confirm Submission</DialogTitle>
              <DialogDescription>
                Are you sure to confirm submission? Auto-submits in {confirmCountdown}s.
              </DialogDescription>
            </DialogHeader>
            <div className="space-y-2">
              <p className="text-sm text-muted-foreground">
                This will save notes to <code>tbl_booking_charges_notes</code> and totals to <code>tbl_booking_charges</code>, then open the receipt.
              </p>
            </div>
            <DialogFooter className="flex gap-2">
              <Button variant="outline" onClick={cancelConfirmModal}>Cancel</Button>
              <Button className="bg-blue-600 text-white hover:bg-blue-700" onClick={confirmNow}>
                Confirm
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>




      {/* Save All button now placed next to Back button above */}

    </>
  )
}

export default CustomerPayment
