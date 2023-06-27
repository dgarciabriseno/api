INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(95, 'GONG Magnetogram', 'GONG Magnetogram',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(95, 'Observatory', 'GONG', 'NSO-GONG', 'GONG', 1),
(95, 'Instrument', 'GONG', 'GONG', 'GONG', 2),
(95, 'Measurement', 'magnetogram', 'magnetogram', 'GONG Magnetogram', 3);
