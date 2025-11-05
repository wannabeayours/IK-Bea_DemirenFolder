import React, { useEffect, useMemo, useState, useRef } from 'react'
import axios from 'axios'
import { toast } from 'sonner'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { NumberFormatter } from '@/pages/admin/Function_Files/NumberFormatter'
import AdminCustomModal from '@/pages/admin/Function_Files/AdminCustomModal'
import { AlertTriangle } from 'lucide-react'

export default function AddEditRoomType({ mode = 'add', room = null, onClose, onSuccess }) {
  const APIConn = `${localStorage.url}admin.php`
  const CustomerAPI = `${localStorage.url}customer.php`
  const isUpdate = mode === 'update'
  const [isLoading, setIsLoading] = useState(false)
  const [newImages, setNewImages] = useState([]) // File[]
  const [existingImages, setExistingImages] = useState([]) // [{imagesroommaster_id, imagesroommaster_filename}]
  const [imagesToDelete, setImagesToDelete] = useState(new Set())
  const [step, setStep] = useState(1)
  const fileInputRef = useRef(null)
  const [showFormatWarning, setShowFormatWarning] = useState(false)
  const [showMissingWarning, setShowMissingWarning] = useState(false)
  const [previewSrc, setPreviewSrc] = useState(null)
  const [previewTitle, setPreviewTitle] = useState('')

  // Dynamic form schema: numeric-only size on add, flexible text on update
  const formSchema = useMemo(() => z.object({
    roomType_name: z.string().min(1, 'Please Input Room Name').max(50),
    roomType_price: z.string()
      .min(1, 'Price is required')
      .regex(/^\d*\.?\d*$/, 'Please enter a valid number')
      .refine((val) => parseFloat(val) > 0, 'Price must be greater than 0'),
    roomType_sizes: isUpdate
      ? z.string().min(1, 'Please provide room size')
      : z.string().min(1, 'Please provide room size').regex(/^\d+$/, 'Only numbers allowed'),
    max_capacity: z.coerce.number().int().min(0, 'Must be 0 or greater'),
    roomtype_beds: z.coerce.number().int().min(0, 'Must be 0 or greater'),
    roomtype_capacity: z.coerce.number().int().min(0, 'Must be 0 or greater'),
    roomtype_maxbeds: z.coerce.number().int().min(0, 'Must be 0 or greater'),
    roomType_desc: z.string().min(10, 'Missing Room Description'),
  }), [isUpdate])

  const defaults = useMemo(() => ({
    roomType_name: isUpdate ? (room?.roomtype_name || '') : '',
    roomType_price: isUpdate ? String(room?.roomtype_price ?? '') : '',
    roomType_sizes: isUpdate ? (room?.roomtype_sizes || '') : '',
    max_capacity: isUpdate ? Number(room?.max_capacity ?? 0) : 1,
    roomtype_beds: isUpdate ? Number(room?.roomtype_beds ?? 0) : 1,
    roomtype_capacity: isUpdate ? Number(room?.roomtype_capacity ?? 0) : 1,
    roomtype_maxbeds: isUpdate ? Number(room?.roomtype_maxbeds ?? 0) : 1,
    roomType_desc: isUpdate ? (room?.roomtype_description || '') : '',
  }), [isUpdate, room])

  const form = useForm({
    resolver: zodResolver(formSchema),
    defaultValues: defaults,
  })

  useEffect(() => {
    form.reset(defaults)
  }, [defaults])

  useEffect(() => {
    if (isUpdate && room?.roomtype_id) {
      loadRoomTypeImages(room.roomtype_id)
    } else {
      setExistingImages([])
      setImagesToDelete(new Set())
    }
  }, [isUpdate, room?.roomtype_id])

  // Prefill all room type details on update from backend
  useEffect(() => {
    const prefillDetails = async () => {
      if (!isUpdate) return
      const id = room?.roomtype_id
      const name = room?.roomtype_name
      try {
        // 1) Prefer a direct fetch from tbl_roomtype via customer API
        if (id) {
          try {
            const fd0 = new FormData()
            fd0.append('method', 'getRoomTypeDetails')
            fd0.append('json', JSON.stringify({ roomTypeId: id }))
            const res0 = await axios.post(CustomerAPI, fd0)
            let det = res0?.data
            if (det && typeof det === 'string') {
              try { det = JSON.parse(det) } catch { det = null }
            }
            if (det && det.roomtype_id) {
              form.reset({
                roomType_name: det.roomtype_name ?? form.getValues('roomType_name'),
                roomType_price: String(det.roomtype_price ?? form.getValues('roomType_price') ?? ''),
                roomType_sizes: det.roomtype_sizes ?? form.getValues('roomType_sizes'),
                max_capacity: Number(det.max_capacity ?? form.getValues('max_capacity') ?? 0),
                roomtype_beds: Number(det.roomtype_beds ?? form.getValues('roomtype_beds') ?? 0),
                roomtype_capacity: Number(det.roomtype_capacity ?? form.getValues('roomtype_capacity') ?? 0),
                roomtype_maxbeds: Number(det.roomtype_maxbeds ?? form.getValues('roomtype_maxbeds') ?? 0),
                roomType_desc: det.roomtype_description ?? form.getValues('roomType_desc'),
              })
              return
            }
          } catch (_) {
            // fall through to existing logic
          }
        }

        const fd = new FormData()
        fd.append('method', 'viewRoomTypes')
        const res = await axios.post(APIConn, fd)
        let data = res.data
        if (typeof data === 'string') {
          try { data = JSON.parse(data) } catch { data = [] }
        }
        const list = Array.isArray(data) ? data : []
        let match = null
        if (id) match = list.find(rt => (rt.roomtype_id ?? rt.id) === id)
        if (!match && name) {
          const target = String(name).toLowerCase()
          match = list.find(rt => String(rt.roomtype_name || '').toLowerCase() === target)
        }
        // Fallback: derive from viewAllRooms if not found
        if (!match && id) {
          try {
            const fd2 = new FormData()
            fd2.append('method', 'viewAllRooms')
            const res2 = await axios.post(APIConn, fd2)
            let roomsData = res2.data
            if (typeof roomsData === 'string') {
              try { roomsData = JSON.parse(roomsData) } catch { roomsData = [] }
            }
            if (Array.isArray(roomsData)) {
              const byId = roomsData.find(r => (r.roomtype_id ?? r.room_type_id) === id)
              if (byId) {
                match = {
                  roomtype_id: id,
                  roomtype_name: byId.roomtype_name,
                  roomtype_description: byId.roomtype_description,
                  roomtype_price: Number(byId.roomtype_price),
                  roomtype_capacity: byId.roomtype_capacity,
                  roomtype_beds: byId.roomtype_beds,
                  roomtype_sizes: byId.roomtype_sizes,
                  roomtype_maxbeds: byId.roomtype_maxbeds,
                  max_capacity: byId.max_capacity,
                }
              }
            }
          } catch {
            // silent fallback failure
          }
        }

        if (match) {
          form.reset({
            roomType_name: match.roomtype_name ?? form.getValues('roomType_name'),
            roomType_price: String(match.roomtype_price ?? form.getValues('roomType_price') ?? ''),
            roomType_sizes: match.roomtype_sizes ?? form.getValues('roomType_sizes'),
            max_capacity: Number(match.max_capacity ?? form.getValues('max_capacity') ?? 0),
            roomtype_beds: Number(match.roomtype_beds ?? form.getValues('roomtype_beds') ?? 0),
            roomtype_capacity: Number(match.roomtype_capacity ?? form.getValues('roomtype_capacity') ?? 0),
            roomtype_maxbeds: Number(match.roomtype_maxbeds ?? form.getValues('roomtype_maxbeds') ?? 0),
            roomType_desc: match.roomtype_description ?? form.getValues('roomType_desc'),
          })
        }
      } catch (err) {
        // keep existing defaults on error
      }
    }
    prefillDetails()
  }, [isUpdate, room?.roomtype_id, room?.roomtype_name])

  const loadRoomTypeImages = async (roomtypeId) => {
    try {
      const fd = new FormData()
      fd.append('method', 'getRoomTypeImages')
      fd.append('json', JSON.stringify({ roomtype_id: roomtypeId }))
      const res = await axios.post(APIConn, fd)
      const list = Array.isArray(res.data) ? res.data : []
      setExistingImages(list)
    } catch (e) {
      setExistingImages([])
    }
  }

  const openImagePreview = (src, title = '') => {
    try {
      setPreviewSrc(src)
      setPreviewTitle(title)
    } catch (_) {}
  }

  const closeImagePreview = () => {
    try {
      if (previewSrc && typeof previewSrc === 'string' && previewSrc.startsWith('blob:')) {
        URL.revokeObjectURL(previewSrc)
      }
    } catch (_) {}
    setPreviewSrc(null)
    setPreviewTitle('')
  }

  const onSubmit = async (values) => {
    if (isUpdate) {
      await updRoomTypes(values)
    } else {
      await addRoomTypes(values)
    }
  }

  const addRoomTypes = async (values) => {
    setIsLoading(true)

    try {
      const jsonData = {
        roomtype_name: values.roomType_name,
        roomtype_description: values.roomType_desc,
        roomtype_price: NumberFormatter.parseCurrencyInput(values.roomType_price),
        max_capacity: Number(values.max_capacity ?? 0),
        roomtype_beds: Number(values.roomtype_beds ?? 0),
        roomtype_capacity: Number(values.roomtype_capacity ?? 0),
        // Append m² when adding new room type
        roomtype_sizes: `${values.roomType_sizes}m²`,
        roomtype_maxbeds: Number(values.roomtype_maxbeds ?? 0),
      }

      const fd = new FormData()
      fd.append('method', 'addRoomType')
      fd.append('json', JSON.stringify(jsonData))
      if (Array.isArray(newImages) && newImages.length > 0) {
        newImages.forEach((file) => fd.append('images[]', file))
      }

      const res = await axios.post(APIConn, fd)
      if (res?.data) {
        toast.success('Successfully added room type!')
        onSuccess?.()
      } else {
        toast.error('Failed to add room type')
      }
    } catch (err) {
      console.error('[AddEditRoomType] addRoomTypes error', err)
      toast.error('Failed to add room type')
    } finally {
      setIsLoading(false)
    }
  }

  const updRoomTypes = async (values) => {
    if (!room?.roomtype_id) {
      toast.error('Missing room type id')
      return
    }
    setIsLoading(true)

    try {
      const jsonData = {
        roomtype_id: room.roomtype_id,
        roomtype_name: values.roomType_name,
        roomtype_description: values.roomType_desc,
        roomtype_price: NumberFormatter.parseCurrencyInput(values.roomType_price),
        max_capacity: Number(values.max_capacity ?? 0),
        roomtype_beds: Number(values.roomtype_beds ?? 0),
        roomtype_capacity: Number(values.roomtype_capacity ?? 0),
        roomtype_sizes: values.roomType_sizes ?? '',
        roomtype_maxbeds: Number(values.roomtype_maxbeds ?? 0),
      }

      const fd = new FormData()
      fd.append('method', 'updateRoomType')
      fd.append('json', JSON.stringify(jsonData))
      if (Array.isArray(newImages) && newImages.length > 0) {
        newImages.forEach((file) => fd.append('images[]', file))
      }
      if (imagesToDelete.size > 0) {
        fd.append('delete_image_ids', JSON.stringify(Array.from(imagesToDelete)))
      }

      const res = await axios.post(APIConn, fd)
      if (res?.data) {
        toast.success('Updated Successfully!')
        onSuccess?.()
      } else {
        toast.error('Failed to update room type')
      }
    } catch (err) {
      console.error('[AddEditRoomType] updRoomTypes error', err)
      toast.error('Failed to update room type')
    } finally {
      setIsLoading(false)
    }
  }

  const isAllowedImage = (file) => {
    const type = (file.type || '').toLowerCase()
    const name = (file.name || '').toLowerCase()
    const extAllowed = name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png')
    const mimeAllowed = type === 'image/jpeg' || type === 'image/png'
    return extAllowed || mimeAllowed
  }

  const handleSelectNewImages = (e) => {
    const incoming = Array.from(e.target.files || [])
    const allowed = []
    let hasInvalid = false
    for (const f of incoming) {
      if (isAllowedImage(f)) allowed.push(f)
      else hasInvalid = true
    }
    if (hasInvalid) {
      setShowFormatWarning(true)
    }
    if (allowed.length > 0) {
      setNewImages((prev) => [...prev, ...allowed])
    }
    // allow re-selecting the same files
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    } else if (e.target) {
      e.target.value = ''
    }
  }

  const removeNewImage = (index) => {
    setNewImages((prev) => prev.filter((_, i) => i !== index))
  }

  const toggleDeleteImage = (imgId) => {
    setImagesToDelete((prev) => {
      const next = new Set(prev)
      if (next.has(imgId)) next.delete(imgId)
      else next.add(imgId)
      return next
    })
  }

  // Validate image binary signatures (JPEG/PNG) before proceeding from Media step
  const checkFileSignature = async (file) => {
    try {
      const blob = file.slice(0, 8)
      const buf = await blob.arrayBuffer()
      const bytes = new Uint8Array(buf)
      // JPEG magic: FF D8 FF
      const isJpeg = bytes[0] === 0xFF && bytes[1] === 0xD8 && bytes[2] === 0xFF
      // PNG magic: 89 50 4E 47 0D 0A 1A 0A
      const isPng =
        bytes.length >= 8 &&
        bytes[0] === 0x89 && bytes[1] === 0x50 && bytes[2] === 0x4E && bytes[3] === 0x47 &&
        bytes[4] === 0x0D && bytes[5] === 0x0A && bytes[6] === 0x1A && bytes[7] === 0x0A
      return isJpeg || isPng
    } catch (e) {
      return false
    }
  }

  const validateMediaStep = async () => {
    // If there are selected new images, ensure type + signature are valid
    if (Array.isArray(newImages) && newImages.length > 0) {
      for (const f of newImages) {
        if (!isAllowedImage(f)) {
          setShowFormatWarning(true)
          toast.warning('Invalid image format detected. Only JPG/PNG are allowed.')
          return false
        }
        const ok = await checkFileSignature(f)
        if (!ok) {
          setShowFormatWarning(true)
          toast.warning('One or more images appear corrupted or unsupported.')
          return false
        }
      }
    }
    return true
  }

  return (
    <div>
      <Form {...form}>
        <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-8">
          {/* Step indicator */}
          <div className="flex items-center gap-2 text-sm">
            <span className={step === 1 ? 'font-semibold' : ''}>1. Basic</span>
            <span>›</span>
            <span className={step === 2 ? 'font-semibold' : ''}>2. Capacity</span>
            <span>›</span>
            <span className={step === 3 ? 'font-semibold' : ''}>3. Media & Desc</span>
            <span>›</span>
            <span className={step === 4 ? 'font-semibold' : ''}>4. Summary</span>
          </div>

          {step === 1 && (
            <div className="space-y-6">
              <div className="flex grid-rows-2 place-content-between gap-4">
                <FormField
                  control={form.control}
                  name="roomType_name"
                  render={({ field }) => (
                    <FormItem className="flex-1">
                      <FormLabel>Room Type Name</FormLabel>
                      <FormControl>
                        <Input placeholder="Room Here..." {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name="roomType_price"
                  render={({ field }) => (
                    <FormItem className="flex-1">
                      <FormLabel>Room Price</FormLabel>
                      <FormControl>
                        <Input placeholder="0.00" {...field} />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <FormField
                control={form.control}
                name="roomType_sizes"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Room Size</FormLabel>
                    <FormControl>
                      {isUpdate ? (
                        <Input placeholder="e.g., 24 m²" {...field} />
                      ) : (
                        <Input type="number" min={0} placeholder="e.g., 24" {...field} />
                      )}
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          )}

          {step === 2 && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <FormField
                control={form.control}
                name="max_capacity"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Max Capacity</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="roomtype_beds"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>No. of Beds</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="roomtype_capacity"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Room Capacity</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
              <FormField
                control={form.control}
                name="roomtype_maxbeds"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Max Beds</FormLabel>
                    <FormControl>
                      <Input type="number" min={0} {...field} />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />
            </div>
          )}

          {step === 3 && (
            <div className="space-y-6">
              <FormField
                control={form.control}
                name="roomType_desc"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Room Description</FormLabel>
                    <FormControl>
                      <Textarea
                        className={isUpdate ? "w-full h-32 max-h-40 overflow-y-auto" : "w-full h-64"}
                        placeholder="Type description here..."
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {/* Existing Images with delete toggles (update only) */}
              {isUpdate && (
                <div className="space-y-2">
                  <FormLabel>Existing Images</FormLabel>
                  {existingImages.length === 0 ? (
                    <div className="text-sm text-gray-500">No images uploaded for this room type.</div>
                  ) : (
                    <div className="mt-2 overflow-x-auto">
                      <div className="flex gap-3">
                        {existingImages.map((img) => {
                          const isChecked = imagesToDelete.has(img.imagesroommaster_id)
                          return (
                            <div key={img.imagesroommaster_id} className="border rounded p-2 w-40 flex-shrink-0">
                              <img
                                src={`${localStorage.url}images/${img.imagesroommaster_filename}`}
                                alt={img.imagesroommaster_filename}
                                className="w-full h-28 object-cover rounded cursor-pointer"
                                onClick={() => openImagePreview(`${localStorage.url}images/${img.imagesroommaster_filename}`, img.imagesroommaster_filename)}
                              />
                              <label className="mt-2 flex items-center gap-2 text-sm">
                                <input
                                  type="checkbox"
                                  checked={isChecked}
                                  onChange={() => toggleDeleteImage(img.imagesroommaster_id)}
                                />
                                Mark for deletion
                              </label>
                            </div>
                          )
                        })}
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* Upload New Images */}
              <div className="space-y-2">
                <FormLabel>Upload Images (optional)</FormLabel>
                <Input
                  ref={fileInputRef}
                  type="file"
                  multiple
                  accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                  onChange={handleSelectNewImages}
                />
                {Array.isArray(newImages) && newImages.length > 0 && (
                  <div className="mt-2 overflow-x-auto">
                    <div className="flex gap-3">
                      {newImages.map((file, idx) => (
                        <div key={idx} className="border rounded p-2 w-40 flex-shrink-0">
                          <img
                            src={URL.createObjectURL(file)}
                            alt={file.name}
                            className="w-full h-28 object-cover rounded cursor-pointer"
                            onClick={() => openImagePreview(URL.createObjectURL(file), file.name)}
                          />
                          <div className="text-xs mt-1 truncate">{file.name}</div>
                          <div className="flex justify-end mt-2">
                            <Button type="button" variant="outline" size="sm" onClick={() => removeNewImage(idx)}>
                              Remove
                            </Button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
                {showFormatWarning && (
                  <div className="mt-2 text-sm text-orange-600">
                    Can only accept the following Image formats: jpg, jpeg and png
                  </div>
                )}
              </div>
            </div>
          )}

          {step === 4 && (
            <div className="space-y-4 text-sm">
              <div><strong>Name:</strong> {form.getValues('roomType_name')}</div>
              <div><strong>Price:</strong> {form.getValues('roomType_price')}</div>
              <div><strong>Size:</strong> {form.getValues('roomType_sizes')}</div>
              <div><strong>Max Capacity:</strong> {form.getValues('max_capacity')}</div>
              <div><strong>Beds:</strong> {form.getValues('roomtype_beds')}</div>
              <div><strong>Room Capacity:</strong> {form.getValues('roomtype_capacity')}</div>
              <div><strong>Max Beds:</strong> {form.getValues('roomtype_maxbeds')}</div>
              <div><strong>Description:</strong> {form.getValues('roomType_desc')}</div>
              <div><strong>New Images:</strong> {newImages.length} selected</div>
            </div>
          )}

          {/* Navigation buttons */}
          <div className="flex justify-between gap-2">
            <Button type="button" variant="outline" onClick={onClose} disabled={isLoading}>
              Cancel
            </Button>
            <div className="flex gap-2">
              {step > 1 && (
                <Button type="button" variant="secondary" onClick={() => setStep((s) => Math.max(1, s - 1))}>
                  Back
                </Button>
              )}
              {step < 4 && (
                <Button
                  type="button"
                  onClick={async () => {
                    // Validate fields for current step before proceeding
                    let fieldsToValidate = []
                    if (step === 1) fieldsToValidate = ['roomType_name', 'roomType_price', 'roomType_sizes']
                    if (step === 2) fieldsToValidate = ['max_capacity', 'roomtype_beds', 'roomtype_capacity', 'roomtype_maxbeds']
                    if (step === 3) fieldsToValidate = ['roomType_desc']
                    const valid = await form.trigger(fieldsToValidate, { shouldFocus: true })
                    if (!valid) {
                      setShowMissingWarning(true)
                      return
                    }
                    if (step === 3) {
                      const mediaOk = await validateMediaStep()
                      if (!mediaOk) return
                    }
                    setStep((s) => Math.min(4, s + 1))
                  }}
                  disabled={isLoading}
                >
                  Next
                </Button>
              )}
              {step === 4 && (
                <Button type="submit" disabled={isLoading}>
                  {isUpdate ? 'Update Room Type' : 'Add Room Type'}
                </Button>
              )}
            </div>
          </div>
        </form>
      </Form>

      {/* Missing data warning — Custom Modal */}
      <AdminCustomModal
        open={showMissingWarning}
        onOpenChange={setShowMissingWarning}
        title={
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-5 h-5 text-amber-600" />
            <span>Warning</span>
          </div>
        }
        contentClassName="max-w-md w-[95vw] sm:w-full"
        description={null}
        footer={
          <div className="mt-4 flex justify-end">
            <Button type="button" onClick={() => setShowMissingWarning(false)}>OK</Button>
          </div>
        }
      >
        <div className="text-sm text-gray-700">
          Warning, some of the data are missing. Please try again
        </div>
      </AdminCustomModal>

      {/* Fullscreen image preview modal */}
      <AdminCustomModal
        open={!!previewSrc}
        onOpenChange={(open) => {
          if (!open) closeImagePreview()
        }}
        title={<span>Image Preview</span>}
        description={previewTitle}
        contentClassName="w-[95vw] h-[95vh] p-0 sm:w-[95vw]"
        footer={
          <div className="p-2 flex justify-end">
            <Button type="button" onClick={closeImagePreview}>Close</Button>
          </div>
        }
      >
        <div className="w-full h-full bg-black flex items-center justify-center">
          {previewSrc ? (
            <img src={previewSrc} alt={previewTitle} className="max-w-full max-h-full object-contain" />
          ) : null}
        </div>
      </AdminCustomModal>
    </div>
  )
}