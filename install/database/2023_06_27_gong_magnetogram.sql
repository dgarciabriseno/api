INSERT INTO datasources (id, name, description, units, layeringOrder, enabled, sourceIdGroup, displayOrder)
VALUES
(37, 'GONG Magnetogram', 'GONG Magnetogram',  NULL, 1, 0, '', 0);

INSERT INTO datasource_property (sourceId, label, name, fitsName, description, uiOrder)
VALUES
(37, 'Observatory', 'GONG', 'NSO-GONG', 'GONG', 1),
(37, 'Instrument', 'GONG', 'GONG', 'GONG', 2),
(37, 'Measurement', 'magnetogram', 'magnetogram', 'GONG Magnetogram', 3);
