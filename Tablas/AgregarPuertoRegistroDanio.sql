-- Agregar columna Puerto a RegistroDanio si no existe
ALTER TABLE RegistroDanio
    ADD COLUMN Puerto VARCHAR(30) NULL;
