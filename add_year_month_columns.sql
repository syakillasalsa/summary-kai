ALTER TABLE laporan
ADD COLUMN tahun INT(4) DEFAULT NULL,
ADD COLUMN bulan INT(2) DEFAULT NULL;

-- Optional: Update existing rows to populate tahun and bulan from input_date
UPDATE laporan
SET tahun = YEAR(input_date),
    bulan = MONTH(input_date)
WHERE input_date IS NOT NULL AND input_date != '0000-00-00';
