import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Separator } from "@/components/ui/separator";
import ComboBox from "@/components/ui/combo-box";
import { Plus, Building, Hash, CheckCircle } from "lucide-react";
import axios from 'axios';

const AddRoomNumberSheet = ({ isOpen, onClose, onRoomAdded }) => {
  const APIConn = `${localStorage.url}admin.php`;
  
  // Form state
  const [formData, setFormData] = useState({
    roomtype_id: '',
    room_floor: '',
    room_status_id: ''
  });
  
  // Options for ComboBoxes
  const [roomTypes, setRoomTypes] = useState([]);
  const [statusTypes, setStatusTypes] = useState([]);
  
  // Loading states
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  // Fetch room types and status types when component mounts
  useEffect(() => {
    if (isOpen) {
      fetchRoomTypes();
      fetchStatusTypes();
    }
  }, [isOpen]);

  const fetchRoomTypes = async () => {
    try {
      setIsLoading(true);
      const response = await axios.post(APIConn, {
        action: 'getRoomTypes'
      });
      
      if (response.data && response.data !== 0) {
        const formattedData = response.data.map((item) => ({
          value: item.roomtype_id,
          label: `${item.roomtype_name} - ₱${item.roomtype_price}`
        }));
        setRoomTypes(formattedData);
      }
    } catch (error) {
      console.error('Error fetching room types:', error);
      toast.error('Failed to load room types');
    } finally {
      setIsLoading(false);
    }
  };

  const fetchStatusTypes = async () => {
    try {
      const response = await axios.post(APIConn, {
        action: 'getStatusTypes'
      });
      
      if (response.data && response.data !== 0) {
        const formattedData = response.data.map((item) => ({
          value: item.status_id,
          label: item.status_name
        }));
        setStatusTypes(formattedData);
      }
    } catch (error) {
      console.error('Error fetching status types:', error);
      toast.error('Failed to load status types');
    }
  };

  const handleInputChange = (field, value) => {
    setFormData(prev => ({
      ...prev,
      [field]: value
    }));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validation
    if (!formData.roomtype_id || !formData.room_floor || !formData.room_status_id) {
      toast.error('Please fill in all required fields');
      return;
    }

    if (isNaN(formData.room_floor) || formData.room_floor < 1) {
      toast.error('Please enter a valid floor number');
      return;
    }

    try {
      setIsSubmitting(true);
      
      const response = await axios.post(APIConn, {
        action: 'addRoomNumber',
        roomtype_id: formData.roomtype_id,
        room_floor: parseInt(formData.room_floor),
        room_status_id: formData.room_status_id
      });

      if (response.data && response.data.success) {
        toast.success('Room number added successfully!');
        
        // Reset form
        setFormData({
          roomtype_id: '',
          room_floor: '',
          room_status_id: ''
        });
        
        // Notify parent component to refresh data
        if (onRoomAdded) {
          onRoomAdded();
        }
        
        // Close sheet
        onClose();
      } else {
        toast.error(response.data?.message || 'Failed to add room number');
      }
    } catch (error) {
      console.error('Error adding room number:', error);
      toast.error('An error occurred while adding the room number');
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleClose = () => {
    // Reset form when closing
    setFormData({
      roomtype_id: '',
      room_floor: '',
      room_status_id: ''
    });
    onClose();
  };

  return (
    <Sheet open={isOpen} onOpenChange={handleClose}>
      <SheetContent className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader className="space-y-3 pb-6">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
              <Plus className="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <SheetTitle className="text-xl font-semibold text-gray-900 dark:text-white">
                Add New Room Number
              </SheetTitle>
              <SheetDescription className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Create a new room number with type, floor, and status
              </SheetDescription>
            </div>
          </div>
        </SheetHeader>

        <Separator className="my-6" />

        <form onSubmit={handleSubmit} className="space-y-6">
          <Card className="border-gray-200 dark:border-gray-700">
            <CardHeader className="pb-4">
              <CardTitle className="text-lg flex items-center gap-2">
                <Building className="h-5 w-5 text-gray-600 dark:text-gray-400" />
                Room Details
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-5">
              {/* Room Type Selection */}
              <div className="space-y-2">
                <Label htmlFor="roomtype" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                  Room Type <span className="text-red-500">*</span>
                </Label>
                <ComboBox
                  list={roomTypes}
                  subject="roomtype"
                  value={formData.roomtype_id}
                  onChange={(value) => handleInputChange('roomtype_id', value)}
                  styles="w-full"
                />
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Select the type of room to create
                </p>
              </div>

              {/* Room Floor Input */}
              <div className="space-y-2">
                <Label htmlFor="floor" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                  Floor Number <span className="text-red-500">*</span>
                </Label>
                <div className="relative">
                  <Hash className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                  <Input
                    id="floor"
                    type="number"
                    min="1"
                    placeholder="Enter floor number (e.g., 1, 2, 3)"
                    value={formData.room_floor}
                    onChange={(e) => handleInputChange('room_floor', e.target.value)}
                    className="pl-10 w-full"
                    required
                  />
                </div>
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Specify which floor this room will be located on
                </p>
              </div>

              {/* Room Status Selection */}
              <div className="space-y-2">
                <Label htmlFor="status" className="text-sm font-medium text-gray-700 dark:text-gray-300">
                  Room Status <span className="text-red-500">*</span>
                </Label>
                <ComboBox
                  list={statusTypes}
                  subject="status"
                  value={formData.room_status_id}
                  onChange={(value) => handleInputChange('room_status_id', value)}
                  styles="w-full"
                />
                <p className="text-xs text-gray-500 dark:text-gray-400">
                  Set the initial status for this room
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Action Buttons */}
          <div className="flex flex-col-reverse sm:flex-row gap-3 pt-4">
            <Button
              type="button"
              variant="outline"
              onClick={handleClose}
              className="w-full sm:w-auto"
              disabled={isSubmitting}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              className="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white"
              disabled={isSubmitting || isLoading}
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                  Adding Room...
                </>
              ) : (
                <>
                  <CheckCircle className="h-4 w-4 mr-2" />
                  Add Room Number
                </>
              )}
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
};

export default AddRoomNumberSheet;