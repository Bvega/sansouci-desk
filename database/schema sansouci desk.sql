-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 10, 2025 at 03:35 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sansouci_desk`
--

-- --------------------------------------------------------

--
-- Table structure for table `asignacion_clientes`
--

CREATE TABLE `asignacion_clientes` (
  `id` int(11) NOT NULL,
  `cliente_email` varchar(255) NOT NULL,
  `agente_id` int(11) NOT NULL,
  `creado_el` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignacion_clientes`
--

INSERT INTO `asignacion_clientes` (`id`, `cliente_email`, `agente_id`, `creado_el`) VALUES
(3, 'tlluberesdom@gmail.com', 15, '2025-11-10 11:52:53');

-- --------------------------------------------------------

--
-- Table structure for table `asignacion_tickets`
--

CREATE TABLE `asignacion_tickets` (
  `id` int(11) NOT NULL,
  `tipo_servicio` varchar(255) NOT NULL,
  `agente_id` int(11) NOT NULL,
  `creado_el` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `asignacion_tickets`
--

INSERT INTO `asignacion_tickets` (`id`, `tipo_servicio`, `agente_id`, `creado_el`) VALUES
(1, 'Consulta General', 18, '2025-12-04 21:31:35');

-- --------------------------------------------------------

--
-- Table structure for table `config_asignacion`
--

CREATE TABLE `config_asignacion` (
  `id` int(11) NOT NULL,
  `modo` enum('carga_trabajo','round_robin') DEFAULT 'carga_trabajo',
  `activa` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_asignacion`
--

INSERT INTO `config_asignacion` (`id`, `modo`, `activa`) VALUES
(1, 'carga_trabajo', 1),
(2, 'carga_trabajo', 1);

-- --------------------------------------------------------

--
-- Table structure for table `config_email`
--

CREATE TABLE `config_email` (
  `id` int(11) NOT NULL,
  `smtp_host` varchar(255) NOT NULL,
  `smtp_port` int(11) NOT NULL DEFAULT 587,
  `smtp_user` varchar(255) NOT NULL,
  `smtp_pass` varchar(255) NOT NULL,
  `smtp_secure` varchar(10) NOT NULL DEFAULT 'tls',
  `from_email` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `config_email`
--

INSERT INTO `config_email` (`id`, `smtp_host`, `smtp_port`, `smtp_user`, `smtp_pass`, `smtp_secure`, `from_email`, `from_name`) VALUES
(1, 'smtp.gmail.com', 587, 'bolivar.vega@gmail.com', 'ightpzupubfrehau ', 'tls', 'bolivar.vega@gmail.com', 'Sansouci Desk');

-- --------------------------------------------------------

--
-- Table structure for table `permisos_modulos`
--

CREATE TABLE `permisos_modulos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `modulo` varchar(50) DEFAULT NULL,
  `permitido` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `permisos_modulos`
--

INSERT INTO `permisos_modulos` (`id`, `user_id`, `modulo`, `permitido`) VALUES
(45, 1, 'dashboard', 1),
(46, 1, 'tickets', 1),
(47, 1, 'reportes', 1),
(48, 1, 'mantenimiento', 1),
(49, 12, 'dashboard', 1),
(50, 12, 'tickets', 1),
(51, 12, 'reportes', 1),
(52, 12, 'mantenimiento', 1),
(134, 16, 'dashboard', 1),
(135, 16, 'tickets', 1),
(136, 16, 'reportes', 1),
(137, 16, 'mantenimiento', 0),
(138, 17, 'dashboard', 1),
(139, 17, 'tickets', 1),
(140, 17, 'reportes', 1),
(141, 17, 'mantenimiento', 0),
(142, 14, 'dashboard', 1),
(143, 14, 'tickets', 1),
(144, 14, 'reportes', 1),
(145, 14, 'mantenimiento', 0),
(146, 15, 'dashboard', 1),
(147, 15, 'tickets', 1),
(148, 15, 'reportes', 1),
(149, 15, 'mantenimiento', 0);

-- --------------------------------------------------------

--
-- Table structure for table `plantillas_respuesta`
--

CREATE TABLE `plantillas_respuesta` (
  `id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `contenido` text NOT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_el` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_el` datetime DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `plantillas_respuesta`
--

INSERT INTO `plantillas_respuesta` (`id`, `titulo`, `contenido`, `creado_por`, `creado_el`, `actualizado_el`, `activo`) VALUES
(1, 'ahora llamamnos', 'este es tu coneccion', NULL, '2025-11-20 20:41:16', NULL, 1),
(4, 'Ticket Recibido', 'Hola, hemos recibido tu solicitud para el ticket {{ticket_numero}} con asunto \"{{ticket_asunto}}\". Nuestro equipo la está revisando.', NULL, '2025-11-22 14:22:58', NULL, 1),
(5, 'ticket recibido', 'este es una prueba', NULL, '2025-11-22 19:06:36', NULL, 1),
(6, 'vbvcvnb', 'cvnvcnv', NULL, '2025-11-22 19:07:53', NULL, 1),
(7, 'enproceso', '{{ticket_numero}}{{ticket_asunto}}{{cliente_email}}{{estado_ticket}}', NULL, '2025-11-22 20:37:26', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `respuestas`
--

CREATE TABLE `respuestas` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `autor` enum('cliente','agente') DEFAULT NULL,
  `autor_email` varchar(100) DEFAULT NULL,
  `creado_el` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `respuestas`
--

INSERT INTO `respuestas` (`id`, `ticket_id`, `mensaje`, `autor`, `autor_email`, `creado_el`) VALUES
(79, 63, 'hemos recibido su respuesta, pronto lo contactaremos.', '', 'admin@local.test', '2025-11-30 20:01:20'),
(80, 64, 'te atendemos despues de ticket 002', '', 'admin@local.test', '2025-12-01 15:56:32'),
(81, 64, 'su ticket va a ser procesado pronto', '', 'admin@local.test', '2025-12-01 16:35:26');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `numero` varchar(20) DEFAULT NULL,
  `cliente_email` varchar(100) DEFAULT NULL,
  `asunto` varchar(200) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `estado` enum('abierto','en_proceso','pendiente_cliente','cerrado') DEFAULT 'abierto',
  `prioridad` enum('baja','normal','alta','urgente') DEFAULT 'normal',
  `agente_id` int(11) DEFAULT NULL,
  `creado_el` datetime DEFAULT current_timestamp(),
  `actualizado_el` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `tipo_servicio` varchar(100) DEFAULT 'General'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `numero`, `cliente_email`, `asunto`, `mensaje`, `estado`, `prioridad`, `agente_id`, `creado_el`, `actualizado_el`, `tipo_servicio`) VALUES
(63, 'TCK-202500001', 'bolivar.vega@gmail.com', 'Consulta', 'Prueba de tickets', 'en_proceso', 'normal', NULL, '2025-11-30 19:49:29', '2025-12-01 16:07:18', 'Consulta General'),
(64, 'TCK-202500002', 'bolivar.vega@gmail.com', 'segungo ticket de prueba', 'seguimos probando', 'en_proceso', 'normal', NULL, '2025-12-01 15:55:36', '2025-12-01 16:35:26', 'Consulta General'),
(65, 'TCK-202500003', 'bolivar.vega@gmail.com', 'tercer ticket de prueba', 'provando cambio a portal_cliente_tickets.php', 'abierto', 'normal', NULL, '2025-12-01 16:34:02', '2025-12-01 16:34:02', 'Consulta General');

--
-- Triggers `tickets`
--
DELIMITER $$
CREATE TRIGGER `generar_numero_ticket` BEFORE INSERT ON `tickets` FOR EACH ROW BEGIN
    IF NEW.numero IS NULL OR NEW.numero = '' THEN
        SET @nuevo_numero = CONCAT('TCK-', YEAR(NOW()), LPAD(
            (SELECT COALESCE(MAX(CAST(SUBSTRING(numero, 9) AS UNSIGNED)), 0) + 1 
             FROM (SELECT numero FROM tickets WHERE YEAR(creado_el) = YEAR(NOW())) AS t), 5, '0'));
        SET NEW.numero = @nuevo_numero;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_stats`
--

CREATE TABLE `ticket_stats` (
  `id` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `abiertos` int(11) DEFAULT 0,
  `cerrados` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tipos_servicio`
--

CREATE TABLE `tipos_servicio` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `creado_el` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tipos_servicio`
--

INSERT INTO `tipos_servicio` (`id`, `nombre`, `descripcion`, `creado_el`) VALUES
(2, 'Facturación', NULL, '2025-11-08 21:18:01'),
(3, 'Reclamación', NULL, '2025-11-08 21:18:01'),
(4, 'Consulta General', NULL, '2025-11-08 21:18:01'),
(7, 'Verificacion BL', NULL, '2025-11-10 12:22:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` enum('agente','administrador','superadmin') DEFAULT 'agente',
  `creado_el` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nombre`, `email`, `password`, `rol`, `creado_el`) VALUES
(1, 'Admin Sansouci', 'admin@sansouci.com.do', '$2y$10$9Z2Y8eX5vB3nK9mP7qR1tO9lU5yT3rF1gH7jK0mN2oP4qS6uV8wXy', 'superadmin', '2025-11-08 18:01:33'),
(12, 'Osmar Lluberes', 'olluberes@zperta.com.do', '$2y$10$RlyaRWCze.F8xrTF.CUggOa8fJ9Ek5A0JjtBh8zHEScj2vBH8PzoG', 'superadmin', '2025-11-08 18:37:07'),
(14, 'agente2', 'agente2@sssp.com', '$2y$10$v5b8evcUFqMTkeUUiOoFGOt98IlowR6QtQjcFLrxTuP.8IoQxg1Ze', 'agente', '2025-11-08 19:35:54'),
(15, 'Agente 3', 'agente3@ssp.com', '$2y$10$A5rAHz6iTZaRIaod2iMOGOWD299vMKHaRwPeFHphtmEOD6iRLTdVi', 'agente', '2025-11-08 19:40:31'),
(16, 'sg@ma.com', 'sg@mf.com', '$2y$10$/ukqOsFKH/N3nMATxnrY8OUSErOpjP2RQfKt3fQ2zIb40K1gBTU5C', 'administrador', '2025-11-08 19:42:12'),
(17, 'ps Admin', 'pa@hd.com', '$2y$10$n.ZCxo1WRwo96rCE1jIzCODpBPQJTVMOHC1kEUV1zXo2ERAf1x002', 'administrador', '2025-11-08 23:42:23'),
(18, 'Admin Local', 'admin@local.test', '$2y$10$D0LgcIiuwagRVHmTiBEO..PAQwILEKOq83SyWuLFA1oyrV8d63qoC', 'superadmin', '2025-11-14 22:57:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `asignacion_clientes`
--
ALTER TABLE `asignacion_clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cliente_email` (`cliente_email`),
  ADD KEY `agente_id` (`agente_id`);

--
-- Indexes for table `asignacion_tickets`
--
ALTER TABLE `asignacion_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tipo_servicio` (`tipo_servicio`),
  ADD KEY `fk_asignacion_agente` (`agente_id`);

--
-- Indexes for table `config_asignacion`
--
ALTER TABLE `config_asignacion`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `config_email`
--
ALTER TABLE `config_email`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permisos_modulos`
--
ALTER TABLE `permisos_modulos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`modulo`);

--
-- Indexes for table `plantillas_respuesta`
--
ALTER TABLE `plantillas_respuesta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_plantillas_creado_por` (`creado_por`);

--
-- Indexes for table `respuestas`
--
ALTER TABLE `respuestas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ticket_id` (`ticket_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `agente_id` (`agente_id`);

--
-- Indexes for table `ticket_stats`
--
ALTER TABLE `ticket_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tipos_servicio`
--
ALTER TABLE `tipos_servicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `asignacion_clientes`
--
ALTER TABLE `asignacion_clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `asignacion_tickets`
--
ALTER TABLE `asignacion_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `config_asignacion`
--
ALTER TABLE `config_asignacion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `permisos_modulos`
--
ALTER TABLE `permisos_modulos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `plantillas_respuesta`
--
ALTER TABLE `plantillas_respuesta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `respuestas`
--
ALTER TABLE `respuestas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `ticket_stats`
--
ALTER TABLE `ticket_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tipos_servicio`
--
ALTER TABLE `tipos_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `asignacion_clientes`
--
ALTER TABLE `asignacion_clientes`
  ADD CONSTRAINT `asignacion_clientes_ibfk_1` FOREIGN KEY (`agente_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `asignacion_tickets`
--
ALTER TABLE `asignacion_tickets`
  ADD CONSTRAINT `fk_asignacion_agente` FOREIGN KEY (`agente_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permisos_modulos`
--
ALTER TABLE `permisos_modulos`
  ADD CONSTRAINT `permisos_modulos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `plantillas_respuesta`
--
ALTER TABLE `plantillas_respuesta`
  ADD CONSTRAINT `fk_plantillas_user` FOREIGN KEY (`creado_por`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `respuestas`
--
ALTER TABLE `respuestas`
  ADD CONSTRAINT `respuestas_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`agente_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
