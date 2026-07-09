-- Übernimmt die bisher hart codierten Shop-Produkte als Startbestand.
-- Einspielen mit: mariadb -u <user> -p <datenbank> < sql/seed.sql

INSERT INTO products (category, title, specs, price, price_note, stock_label, is_featured, sort_order) VALUES
('pc', 'JOTECH Ryzen Workstation', 'Ryzen 5 5600 · 16GB DDR4\n512GB NVMe · RTX 3060 12GB', 899.00, '12 Mon. Gewährleistung', 'Verfügbar', 1, 1),
('pc', 'Büro-PC Slim i5', 'Intel i5-10500 · 8GB RAM\n256GB SSD · Intel UHD', 379.00, '12 Mon. Gewährleistung', 'Verfügbar', 0, 2),
('laptop', 'ThinkPad T14 (Refurbished)', 'Intel i5 · 16GB RAM\n256GB SSD · FHD IPS', 549.00, '12 Mon. Gewährleistung', 'Verfügbar', 1, 3),
('laptop', 'ASUS TUF A15 Gaming', 'Ryzen 7 · 16GB RAM · RTX 3050\n512GB SSD · 144Hz Display', 729.00, '12 Mon. Gewährleistung', 'Nur 1x verfügbar', 0, 4),
('laptop', 'Dell XPS 13 (2020)', 'Intel i7 · 16GB RAM\n512GB SSD · 4K Touch', 649.00, '12 Mon. Gewährleistung', 'Verfügbar', 0, 5),
('komponente', 'NVIDIA RTX 4070 · 12GB', 'Geprüft & Stresstest bestanden\n6 Monate Gewährleistung', 459.00, '6 Mon. Gewähr.', 'Nur 2x verfügbar', 1, 6),
('komponente', 'Kingston 16GB DDR4 Kit', '2x8GB · 3200MHz\nGetestet mit MemTest86', 39.00, '6 Mon. Gewähr.', 'Verfügbar', 0, 7),
('zubehoer', 'Mechanische Tastatur RGB', 'Blue-Switches · DE-Layout\nInkl. Handballenauflage', 45.00, '3 Mon. Gewähr.', 'Verfügbar', 0, 8),
('zubehoer', '27" Full-HD 75Hz', 'IPS-Panel · HDMI/DP\nHöhenverstellbarer Fuß', 119.00, '6 Mon. Gewähr.', 'Verfügbar', 0, 9);
