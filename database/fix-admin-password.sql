-- Run in phpMyAdmin if admin / admin123 does not work (existing database only).
-- New installs: use database/schema.sql instead.

USE inbound_shipment_db;

UPDATE users
SET password_hash = '$2y$10$.MYp03P7v1ticvPDY95XBuXUO3sNW5GSWoI1yyENpBFDtlFm9IAiq'
WHERE username = 'admin' AND role = 'admin';
