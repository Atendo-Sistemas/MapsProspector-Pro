-- Corrige a senha do usuário super_admin (admin@atendo.maps) para: admin123
-- O hash no seed antigo era para "password", não "admin123". Execute uma vez no banco maps.

USE `maps`;

UPDATE `users`
SET `password` = '$2y$10$Cn/9BTVZhe2u21oncyPFROwXd0gFbn01BSzqGeqRbOD1no9WpHqfm'
WHERE `email` = 'admin@atendo.maps'
LIMIT 1;
