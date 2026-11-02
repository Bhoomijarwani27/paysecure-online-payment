-- Add VPA column to create_account table to support UPI ID payments
ALTER TABLE create_account 
ADD COLUMN vpa VARCHAR(100) UNIQUE DEFAULT NULL AFTER phone_number;

-- Update existing users with auto-generated VPAs based on their phone numbers
-- Format: phonenumber@paysecure
UPDATE create_account 
SET vpa = CONCAT(phone_number, '@paysecure') 
WHERE vpa IS NULL AND phone_number IS NOT NULL;
