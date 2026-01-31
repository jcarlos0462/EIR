-- Tabla para registrar daños asociados a un vehículo
CREATE TABLE IF NOT EXISTS RegistroDanio (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    VIN VARCHAR(50) NOT NULL,
    AreaID INT NOT NULL,
    TipoID INT NOT NULL,
    SeveridadID INT NOT NULL,
    FechaRegistro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (VIN) REFERENCES vehiculo(VIN),
    FOREIGN KEY (AreaID) REFERENCES areadano(ID),
    FOREIGN KEY (TipoID) REFERENCES tipodano(ID),
    FOREIGN KEY (SeveridadID) REFERENCES severidaddano(ID)
);
