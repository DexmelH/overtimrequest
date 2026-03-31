-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 31, 2026 at 10:00 AM
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
-- Database: `kdtphdb_new`
--

-- --------------------------------------------------------

--
-- Table structure for table `department_list`
--

CREATE TABLE `department_list` (
  `id` int(11) NOT NULL,
  `abbreviation` varchar(5) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `department_list`
--

INSERT INTO `department_list` (`id`, `abbreviation`, `name`) VALUES
(1, 'ADM', 'Admin/Accounting'),
(2, 'SOL', 'Solution'),
(3, 'IND', 'Industrial'),
(4, 'PJ', 'Project'),
(5, 'ENV', 'Environmental'),
(6, 'MH', 'Materials Handling'),
(7, 'EE', 'Electrical'),
(8, 'BOI', 'Boiler'),
(9, 'HYD', 'Hydrogen'),
(10, 'KRM', 'Kawasaki Railcar Manufacturing');

-- --------------------------------------------------------

--
-- Table structure for table `designation_list`
--

CREATE TABLE `designation_list` (
  `id` int(11) NOT NULL,
  `acronym` varchar(10) NOT NULL,
  `name` varchar(50) NOT NULL,
  `section` int(11) NOT NULL,
  `priority` int(3) NOT NULL,
  `show_man_sum` int(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `designation_list`
--

INSERT INTO `designation_list` (`id`, `acronym`, `name`, `section`, `priority`, `show_man_sum`) VALUES
(1, 'SSV', 'Senior Supervisor', 1, 7, 1),
(2, 'SV', 'Supervisor', 1, 8, 1),
(3, 'SDE', 'Senior Design Engineer', 2, 1, 1),
(4, 'DE3', 'Design Engineer 3', 2, 2, 1),
(5, 'DE2', 'Design Engineer 2', 2, 3, 1),
(6, 'DE1', 'Design Engineer 1', 2, 4, 1),
(7, 'ADE', 'Assistant Design Engineer', 2, 5, 1),
(8, 'PDE', 'Probationary Design Engineer', 2, 6, 1),
(9, 'CDE', 'Contractual Design Engineer', 2, 0, 0),
(10, 'CSV', 'CAD Supervisor', 2, 0, 0),
(11, 'CS', 'CAD Specialist', 2, 0, 0),
(12, 'SCO', 'Senior CAD Operator', 2, 0, 0),
(13, 'CO2', 'CAD Operator 2', 2, 0, 0),
(14, 'CO1', 'CAD Operator 1', 2, 0, 0),
(15, 'ACO', 'Assistant CAD Operator', 2, 0, 0),
(16, 'IT-E1', 'IT Engineer 1', 3, 5, 1),
(17, 'AM', 'Assistant Manager', 1, 4, 1),
(18, 'DM', 'Department Manager', 1, 3, 1),
(19, 'SM', 'Senior Manager', 1, 2, 1),
(20, 'PSE', 'Probationary Software Engineer', 4, 10, 1),
(21, 'SSS', 'Senior Software Supervisor', 4, 3, 1),
(22, 'SSE', 'Senior Software Engineer', 4, 5, 1),
(23, 'SE1', 'Software Engineer 1', 4, 8, 1),
(24, 'SE2', 'Software Engineer 2', 4, 7, 1),
(25, 'SE3', 'Software Engineer 3', 4, 6, 1),
(26, 'SDM', 'Software Developer Manager', 4, 1, 1),
(27, 'ASM', 'Asst. Software Manager', 4, 2, 1),
(28, 'JSS', 'Jr. Software Supervisor', 4, 4, 1),
(29, 'KDTP', 'KDT President', 1, 0, 0),
(30, 'IT-E2', 'IT Engineer 2', 3, 4, 1),
(31, 'IT-E3', 'IT-Engineer 3', 3, 3, 1),
(34, 'IT-SS', 'IT Support Staff', 3, 6, 1),
(36, 'IT-SV', 'IT Supervisor', 3, 1, 1),
(37, 'ASE', 'Assistant Software Engineer', 4, 9, 1),
(40, 'MJ', 'Messenger/Janitor', 5, 5, 1),
(41, 'ASS', 'Admin Staff/Secretary', 5, 3, 1),
(42, 'AAR', 'Admin Assistant/Receptionist', 5, 4, 1),
(43, 'DR2', 'Company Driver 2', 5, 6, 1),
(44, 'DR', 'Company Driver', 5, 7, 1),
(45, 'DRM', 'Company Driver/Messenger', 5, 10, 1),
(46, 'SA', 'Senior Accountant', 5, 9, 1),
(47, 'SAA', 'Senior Accounting Assistant', 5, 11, 1),
(48, 'AA', 'Accounting Assistant', 5, 13, 1),
(49, 'CSAD', 'Contractual Senior Advisor', 5, 1, 1),
(50, 'KDTP', 'President', 1, 0, 0),
(51, 'CTE', 'Contractual Technical Expert', 1, 5, 1),
(52, 'IT-SE', 'IT Senior Engineer', 3, 2, 1),
(53, 'CSE', 'Contractual Supervising Engineer', 2, 7, 1),
(54, 'PIT-SS', 'Probationary IT Support Staff', 3, 7, 1),
(55, 'GM', 'General Manager', 1, 1, 1),
(56, 'BK', 'Book Keeper', 5, 16, 1),
(57, 'SBK', 'Senior Book Keeper', 5, 15, 1),
(58, 'JA', 'Junior Accountant', 5, 14, 1),
(59, 'DR1', 'Company Driver 1', 5, 12, 1),
(60, 'SAAS', 'Senior Admin Asst/Secretary', 5, 2, 1),
(61, 'SAAR', 'Senior Admin Assistant/Receptionist', 5, 8, 1),
(62, 'M', 'Manager', 1, 6, 1),
(63, 'KDTVP', 'Vice President', 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_list`
--

CREATE TABLE `dispatch_list` (
  `id` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `location` int(11) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispatch_list`
--

INSERT INTO `dispatch_list` (`id`, `emp_id`, `location`, `date_from`, `date_to`, `date_created`, `status`) VALUES
(2, 510, 8, '2025-07-16', '2025-07-16', '2025-07-11 10:00:48', 1),
(3, 510, 8, '2025-07-17', '2025-07-17', '2025-07-11 10:00:48', 1),
(4, 510, 8, '2025-07-21', '2025-07-21', '2025-07-11 10:00:48', 1),
(5, 510, 8, '2025-07-23', '2025-07-23', '2025-07-11 10:00:48', 1),
(6, 510, 8, '2025-07-24', '2025-07-24', '2025-07-11 10:00:48', 1),
(7, 510, 8, '2025-07-28', '2025-07-28', '2025-07-11 16:26:39', 1),
(8, 510, 8, '2025-07-30', '2025-07-30', '2025-07-11 16:28:37', 1),
(9, 510, 8, '2025-07-31', '2025-07-31', '2025-07-11 16:30:06', 1),
(11, 487, 8, '2025-07-14', '2025-07-14', '2025-07-15 10:49:19', 0),
(12, 464, 8, '2025-07-21', '2025-07-21', '2025-07-18 13:14:04', 1),
(13, 464, 8, '2025-07-23', '2025-07-23', '2025-07-18 13:14:04', 1),
(14, 464, 8, '2025-07-24', '2025-07-24', '2025-07-18 13:14:04', 1),
(15, 464, 8, '2025-07-28', '2025-07-28', '2025-07-18 13:14:04', 1),
(16, 464, 8, '2025-07-30', '2025-07-30', '2025-07-18 13:14:04', 1),
(17, 464, 8, '2025-07-31', '2025-07-31', '2025-07-18 13:14:04', 1),
(18, 487, 8, '2025-07-23', '2025-07-23', '2025-07-18 16:49:57', 1);

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_list_history`
--

CREATE TABLE `dispatch_list_history` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `edited_by` int(11) NOT NULL,
  `date_edited` datetime NOT NULL DEFAULT current_timestamp(),
  `old_data` varchar(255) NOT NULL,
  `new_data` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_list_logs`
--

CREATE TABLE `dispatch_list_logs` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `details` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date_created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dispatch_list_logs`
--

INSERT INTO `dispatch_list_logs` (`id`, `dispatch_id`, `details`, `user_id`, `date_created`) VALUES
(1, 12, 'Added', 510, '2025-07-18 13:14:04'),
(2, 13, 'Added', 510, '2025-07-18 13:14:04'),
(3, 14, 'Added', 510, '2025-07-18 13:14:04'),
(4, 15, 'Added', 510, '2025-07-18 13:14:04'),
(5, 16, 'Added', 510, '2025-07-18 13:14:04'),
(6, 17, 'Added', 510, '2025-07-18 13:14:04'),
(7, 2, 'Edited', 510, '2025-07-18 13:30:06'),
(8, 18, 'Added', 510, '2025-07-18 16:49:57'),
(9, 18, 'Edited', 510, '2025-07-18 16:50:49'),
(10, 18, 'Edited', 510, '2025-07-18 16:55:35'),
(11, 15, 'Edited', 510, '2025-07-18 16:55:55');

-- --------------------------------------------------------

--
-- Table structure for table `employee_group`
--

CREATE TABLE `employee_group` (
  `id` int(11) NOT NULL,
  `employee_number` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_group`
--

INSERT INTO `employee_group` (`id`, `employee_number`, `group_id`) VALUES
(1, 7, 2),
(2, 8, 2),
(3, 10, 3),
(4, 10, 2),
(5, 10, 5),
(6, 10, 20),
(7, 10, 15),
(8, 10, 10),
(9, 10, 16),
(10, 10, 14),
(11, 10, 9),
(12, 10, 19),
(13, 18, 3),
(14, 18, 10),
(15, 18, 16),
(16, 18, 14),
(17, 21, 2),
(18, 25, 4),
(19, 25, 7),
(20, 25, 6),
(21, 25, 13),
(22, 25, 9),
(23, 25, 11),
(24, 25, 14),
(25, 25, 19),
(26, 30, 12),
(27, 30, 14),
(28, 30, 0),
(29, 34, 2),
(30, 37, 18),
(31, 37, 0),
(32, 37, 14),
(33, 40, 8),
(34, 40, 14),
(35, 43, 13),
(36, 43, 6),
(37, 43, 11),
(38, 43, 9),
(39, 55, 1),
(40, 61, 3),
(41, 61, 10),
(42, 101, 2),
(43, 104, 14),
(44, 104, 8),
(45, 104, 18),
(46, 104, 4),
(47, 104, 7),
(48, 104, 6),
(49, 104, 9),
(50, 104, 13),
(51, 104, 12),
(52, 104, 11),
(53, 104, 0),
(54, 107, 6),
(55, 107, 9),
(56, 107, 11),
(57, 107, 13),
(58, 117, 8),
(59, 121, 2),
(60, 122, 2),
(62, 134, 5),
(63, 134, 15),
(66, 134, 3),
(67, 134, 10),
(68, 134, 16),
(69, 145, 7),
(70, 158, 20),
(71, 158, 14),
(72, 172, 12),
(73, 173, 20),
(74, 174, 20),
(75, 185, 15),
(76, 194, 12),
(77, 209, 6),
(78, 209, 9),
(79, 209, 11),
(80, 212, 16),
(81, 215, 6),
(82, 215, 9),
(83, 215, 11),
(84, 221, 8),
(85, 222, 13),
(86, 223, 13),
(87, 224, 8),
(88, 226, 20),
(89, 230, 12),
(90, 238, 8),
(91, 240, 6),
(92, 243, 1),
(93, 252, 5),
(94, 256, 0),
(95, 256, 6),
(96, 257, 13),
(97, 259, 6),
(98, 260, 9),
(99, 261, 4),
(100, 262, 12),
(101, 262, 0),
(102, 263, 12),
(103, 264, 7),
(104, 265, 8),
(105, 268, 15),
(106, 268, 19),
(107, 270, 15),
(108, 272, 15),
(109, 279, 11),
(110, 279, 9),
(111, 281, 4),
(112, 283, 20),
(113, 284, 20),
(114, 284, 19),
(115, 288, 20),
(116, 290, 7),
(117, 291, 4),
(118, 293, 9),
(119, 295, 12),
(120, 296, 8),
(121, 299, 18),
(122, 299, 0),
(123, 301, 18),
(124, 301, 0),
(125, 302, 20),
(126, 302, 19),
(127, 304, 8),
(128, 304, 4),
(129, 305, 15),
(130, 306, 15),
(131, 306, 19),
(132, 307, 10),
(133, 310, 13),
(134, 311, 18),
(135, 312, 18),
(136, 313, 20),
(137, 313, 19),
(138, 314, 20),
(139, 316, 6),
(140, 320, 20),
(141, 321, 20),
(142, 322, 20),
(143, 323, 18),
(144, 328, 18),
(145, 329, 18),
(146, 330, 15),
(147, 332, 8),
(148, 333, 15),
(149, 333, 19),
(150, 334, 8),
(151, 335, 12),
(152, 335, 0),
(153, 338, 8),
(154, 338, 19),
(155, 344, 4),
(156, 345, 7),
(157, 346, 20),
(158, 348, 8),
(159, 350, 7),
(160, 352, 8),
(161, 353, 16),
(162, 353, 0),
(163, 355, 16),
(164, 356, 20),
(165, 357, 15),
(166, 358, 15),
(167, 364, 8),
(168, 365, 8),
(169, 367, 8),
(170, 370, 10),
(171, 371, 12),
(172, 371, 0),
(173, 372, 20),
(174, 373, 7),
(175, 374, 0),
(176, 376, 20),
(177, 377, 12),
(178, 378, 12),
(179, 381, 12),
(180, 381, 0),
(181, 382, 5),
(182, 383, 5),
(183, 384, 3),
(184, 385, 5),
(185, 386, 11),
(186, 387, 12),
(187, 388, 12),
(188, 389, 6),
(189, 390, 8),
(190, 391, 1),
(191, 393, 12),
(192, 394, 12),
(193, 395, 8),
(194, 396, 12),
(195, 397, 18),
(196, 398, 2),
(197, 401, 8),
(198, 402, 18),
(199, 403, 6),
(200, 404, 8),
(201, 406, 6),
(202, 407, 20),
(203, 409, 16),
(204, 410, 18),
(205, 411, 8),
(206, 412, 8),
(207, 413, 12),
(208, 414, 8),
(209, 415, 8),
(210, 416, 9),
(211, 417, 12),
(212, 419, 2),
(213, 420, 8),
(214, 421, 12),
(215, 422, 8),
(216, 423, 8),
(217, 424, 12),
(218, 425, 8),
(219, 426, 18),
(220, 427, 12),
(221, 427, 0),
(222, 428, 8),
(223, 429, 8),
(224, 430, 8),
(225, 432, 8),
(226, 433, 12),
(227, 434, 8),
(228, 435, 8),
(229, 436, 8),
(230, 437, 8),
(231, 438, 8),
(232, 439, 10),
(233, 440, 10),
(234, 444, 5),
(235, 445, 3),
(236, 446, 2),
(237, 447, 15),
(238, 448, 15),
(239, 449, 15),
(240, 449, 19),
(241, 450, 15),
(242, 451, 15),
(243, 452, 3),
(244, 452, 19),
(245, 454, 18),
(246, 455, 8),
(247, 456, 8),
(248, 459, 8),
(249, 460, 8),
(250, 461, 8),
(251, 462, 8),
(252, 463, 8),
(253, 464, 16),
(254, 464, 8),
(255, 464, 20),
(256, 464, 10),
(257, 464, 6),
(258, 464, 2),
(259, 465, 16),
(260, 466, 16),
(261, 467, 18),
(262, 468, 13),
(263, 469, 13),
(264, 470, 12),
(265, 471, 15),
(266, 472, 6),
(267, 473, 8),
(268, 474, 8),
(269, 475, 15),
(270, 477, 15),
(271, 478, 12),
(272, 479, 8),
(273, 480, 18),
(274, 481, 8),
(275, 482, 15),
(276, 483, 8),
(277, 484, 20),
(278, 485, 5),
(279, 486, 4),
(280, 487, 16),
(281, 487, 8),
(282, 487, 20),
(283, 487, 10),
(284, 488, 16),
(285, 489, 12),
(286, 489, 0),
(287, 490, 12),
(288, 491, 12),
(289, 492, 12),
(290, 493, 12),
(291, 494, 12),
(292, 495, 12),
(293, 496, 12),
(294, 497, 2),
(295, 498, 16),
(296, 498, 19),
(297, 499, 7),
(298, 500, 8),
(299, 501, 15),
(300, 502, 8),
(301, 503, 18),
(302, 504, 4),
(303, 505, 6),
(304, 506, 4),
(305, 507, 13),
(306, 508, 15),
(307, 509, 20),
(308, 510, 16),
(309, 510, 20),
(310, 510, 14),
(311, 511, 16),
(312, 513, 16),
(313, 514, 10),
(314, 515, 4),
(315, 515, 19),
(316, 516, 8),
(317, 517, 16),
(318, 518, 16),
(319, 520, 16),
(320, 521, 16),
(321, 10008, 0),
(322, 10018, 0),
(323, 10035, 0),
(324, 20001, 2),
(325, 20002, 13),
(326, 20002, 6),
(327, 20002, 11),
(328, 20002, 9),
(329, 20003, 19),
(330, 20003, 15),
(331, 30001, 0),
(332, 134, 14),
(333, 134, 19),
(334, 134, 20),
(335, 30040, 2),
(336, 537, 4),
(337, 522, 5),
(338, 523, 5),
(339, 533, 12),
(340, 527, 8),
(341, 531, 8),
(342, 534, 8),
(343, 544, 10),
(344, 528, 12),
(345, 530, 12),
(346, 532, 12),
(347, 535, 12),
(348, 538, 15),
(349, 529, 15),
(350, 539, 16),
(351, 540, 16),
(352, 541, 16),
(353, 542, 16),
(354, 543, 16),
(356, 536, 18),
(357, 524, 20),
(358, 525, 20),
(359, 526, 20),
(360, 510, 10),
(361, 279, 12),
(362, 545, 2),
(363, 30041, 20),
(364, 30042, 20),
(365, 30043, 9),
(366, 30046, 8),
(367, 30044, 12),
(368, 30045, 12),
(369, 546, 2),
(370, 510, 13),
(371, 510, 4),
(372, 510, 7),
(373, 439, 20),
(374, 547, 6),
(375, 548, 13),
(376, 549, 7),
(377, 550, 12),
(378, 551, 12),
(379, 552, 12),
(380, 553, 12),
(381, 554, 12),
(382, 555, 12),
(383, 556, 8),
(384, 557, 8),
(385, 558, 8),
(386, 559, 8),
(387, 560, 18),
(388, 561, 18),
(389, 563, 15),
(390, 564, 15),
(391, 565, 15),
(392, 566, 15),
(393, 567, 15),
(394, 568, 20),
(395, 562, 15),
(396, 569, 8),
(397, 570, 12),
(398, 30063, 2),
(399, 212, 22),
(400, 134, 22),
(401, 572, 12),
(402, 573, 12),
(403, 575, 2),
(404, 576, 23),
(405, 577, 23),
(406, 578, 23),
(407, 579, 23),
(408, 571, 12),
(409, 574, 12),
(410, 580, 2),
(411, 510, 12),
(412, 40, 23),
(413, 464, 12);

-- --------------------------------------------------------

--
-- Table structure for table `employee_list`
--

CREATE TABLE `employee_list` (
  `id` int(11) NOT NULL,
  `surname` varchar(60) NOT NULL,
  `firstname` varchar(60) NOT NULL,
  `nickname` varchar(60) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `group_id` int(11) DEFAULT NULL,
  `designation` int(11) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` int(11) NOT NULL,
  `marital_status` int(11) NOT NULL,
  `date_hired` date NOT NULL,
  `emp_status` int(11) NOT NULL DEFAULT 1,
  `resignation_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_list`
--

INSERT INTO `employee_list` (`id`, `surname`, `firstname`, `nickname`, `username`, `email`, `group_id`, `designation`, `birthdate`, `gender`, `marital_status`, `date_hired`, `emp_status`, `resignation_date`) VALUES
(7, 'Laureano', 'Antonio', 'Toni', 'toni1', '', 2, 49, '1956-07-05', 0, 1, '1989-05-11', 0, '2021-07-01'),
(8, 'Panado', 'Evangeline', 'Van', 'van', 'panado-g1@global.kawasaki.com', 2, 18, '1960-07-03', 1, 1, '1990-06-11', 1, '2025-07-30'),
(10, 'Tan', 'Erwin', 'TAN', 'tan-g1', 'tan-g1@global.kawasaki.com', 14, 55, '1965-12-20', 0, 1, '1989-05-11', 1, '0000-00-00'),
(18, 'Matibag', 'Erwilson', 'Wilson', 'wilson', 'matibag-kdt@global.kawasaki.com', 14, 18, '1972-10-16', 0, 1, '1995-06-01', 0, '2024-10-16'),
(21, 'Santos', 'Roberto', 'Bert', 'kuyabert', '', 2, 40, '1962-10-14', 0, 1, '1996-09-01', 1, '0000-00-00'),
(25, 'Derigay', 'Oliver', 'Oliver', 'derigay-g1', 'derigay-g1@global.kawasaki.com', 4, 18, '1971-10-21', 0, 1, '1997-01-06', 1, '0000-00-00'),
(30, 'Abuan', 'Christopher', 'Fher', 'abuan-kdt', 'abuan-kdt@global.kawasaki.com', 12, 18, '1975-12-06', 0, 1, '1997-01-09', 1, '0000-00-00'),
(34, 'Corpuz', 'Antonio', 'Tony', 'bosstony', '', 2, 43, '1965-05-30', 0, 1, '1997-02-01', 1, '0000-00-00'),
(37, 'Llanes', 'Ferdinand', 'Ferdie', 'llanes-kdt', 'llanes-kdt@global.kawasaki.com', 18, 18, '1969-10-31', 0, 1, '1997-08-15', 1, '0000-00-00'),
(40, 'Cabello', 'Aristeo', 'Aris', 'cabello-kdt', 'cabello-kdt@global.kawasaki.com', 8, 18, '1976-01-24', 0, 1, '1997-10-16', 1, '0000-00-00'),
(43, 'Ampig', 'Rommel', 'Rommel', 'rommel1', '', 13, 18, '1972-01-15', 0, 1, '1998-01-15', 0, '2022-04-01'),
(55, 'Perez', 'April', 'April', 'perez-kdt', 'perez-kdt@global.kawasaki.com', 1, 46, '1976-04-09', 1, 0, '1998-03-23', 1, '0000-00-00'),
(61, 'Balisbis Jr.', 'Cezar', 'Czar', 'czar', 'balisbis-kdt@global.kawasaki.com', 3, 17, '1974-09-18', 0, 1, '1998-04-16', 1, '0000-00-00'),
(101, 'Soriano', 'Sharon Ann', 'Ann', 'soriano-kdt', 'soriano-kdt@global.kawasaki.com', 2, 41, '1976-12-12', 1, 1, '2007-03-03', 1, '0000-00-00'),
(104, 'Veloso', 'Ernesto', 'Lou', 'veloso-kdt', 'veloso-kdt@global.kawasaki.com', 14, 19, '1967-03-24', 0, 1, '2000-07-03', 1, '0000-00-00'),
(107, 'Diaz', 'Lorenzo', 'Lorenz', 'diaz-kdt', 'diaz-kdt@global.kawasaki.com', 6, 18, '1974-11-06', 0, 1, '2000-10-16', 1, '0000-00-00'),
(117, 'Vitalicio', 'Mae Anne', 'Mae', 'vitalicio-kdt', 'vitalicio-kdt@global.kawasaki.com', 8, 17, '1976-11-28', 1, 0, '2002-07-01', 1, '0000-00-00'),
(121, 'Derigay', 'Arlene', 'Arlene', 'magno-kdt', 'magno-kdt@global.kawasaki.com', 2, 61, '1977-11-20', 1, 1, '2002-01-02', 1, '0000-00-00'),
(122, 'Fabia', 'Roderick', 'Rick', 'kuyarick', '', 2, 44, '1970-03-08', 0, 1, '2004-08-18', 1, '0000-00-00'),
(134, 'Becina', 'Artemio Roel', 'Brix', 'becina-kdt', 'becina-kdt@global.kawasaki.com', 15, 18, '1978-04-10', 0, 1, '2002-09-02', 1, '0000-00-00'),
(145, 'Baradas', 'Ryan', 'Ryan', 'baradas-kdt', 'baradas-kdt@global.kawasaki.com', 7, 1, '1981-03-25', 0, 1, '2005-04-18', 1, '0000-00-00'),
(158, 'Valdez', 'Ramir', 'Ramir', 'valdez-kdt', 'valdez-kdt@global.kawasaki.com', 20, 18, '1965-11-25', 0, 1, '2006-04-03', 1, '0000-00-00'),
(172, 'Viray', 'Jermaine', 'Maine', 'maine', 'marasigan-kdt@global.kawasaki.com', 12, 1, '1982-09-20', 1, 1, '2006-05-15', 0, '2022-12-31'),
(173, 'Aguilar', 'John Isaac', 'Ice', 'aguilar-kdt', 'aguilar-kdt@global.kawasaki.com', 20, 1, '1983-12-24', 0, 1, '2006-05-22', 1, '0000-00-00'),
(174, 'Apostol', 'Roel Boy', 'Putol', 'roel', 'apostol-kdt@global.kawasaki.com', 20, 1, '1983-06-20', 0, 1, '2006-08-22', 1, '0000-00-00'),
(185, 'De Jesus', 'Ryan', 'Ryan', 'ryanm', 'dejesus-kdt@global.kawasaki.com', 15, 17, '1982-08-25', 0, 1, '2006-11-16', 1, '0000-00-00'),
(194, 'Castuera', 'Maxwell', 'Max', 'castuera-kdt', 'castuera-kdt@global.kawasaki.com', 12, 1, '1980-03-07', 0, 0, '2007-01-02', 1, '2024-07-04'),
(209, 'Leal Jr.', 'Ernesto', 'Leal', 'ernesto', 'leal-kdt@global.kawasaki.com', 6, 1, '1982-09-28', 0, 1, '2007-03-01', 1, '0000-00-00'),
(212, 'Lazaro', 'Edmon', 'Mon', 'edmon', 'lazaro-kdt@global.kawasaki.com', 22, 21, '1984-10-20', 0, 1, '2007-03-21', 1, '0000-00-00'),
(215, 'Palmones', 'Michael', 'Mike', 'palmones-kdt', 'palmones-kdt@global.kawasaki.com', 6, 1, '1979-12-12', 0, 0, '2007-03-21', 1, '2025-03-28'),
(221, 'Camato Jr.', 'Gerardo', 'Gerald', 'camato-kdt', 'camato-kdt@global.kawasaki.com', 8, 1, '1984-10-14', 0, 1, '2007-05-16', 1, '0000-00-00'),
(222, 'Beltran', 'Jeffrey', 'Jeff', 'jeffrey', 'beltran-kdt@global.kawasaki.com', 13, 1, '1983-12-30', 0, 0, '2007-05-16', 1, '0000-00-00'),
(223, 'Pahel', 'Jobert', 'Jobs', 'jobert', 'pahel-kdt@global.kawasaki.com', 13, 1, '1982-08-21', 0, 1, '2007-05-16', 1, '0000-00-00'),
(224, 'Desoloc', 'Blessel', 'Bles', 'desoloc-kdt', 'desoloc-kdt@global.kawasaki.com', 8, 1, '1985-03-29', 1, 0, '2007-05-16', 1, '0000-00-00'),
(226, 'Gulapa', 'Francis Martee', 'Martee', 'gulapa-kdt', 'gulapa-kdt@global.kawasaki.com', 20, 1, '1985-01-23', 0, 1, '2007-05-16', 1, '0000-00-00'),
(230, 'Oliveros', 'Mark Anthony', 'Mark', 'oliveros-kdt', 'oliveros-kdt@global.kawasaki.com', 12, 1, '1983-04-16', 0, 1, '2007-06-25', 1, '0000-00-00'),
(238, 'Sese', 'Joseph Ferdinand', 'Joseph', 'sese-kdt', 'sese-kdt@global.kawasaki.com', 8, 2, '1973-09-11', 0, 1, '2007-08-16', 1, '0000-00-00'),
(240, 'Miranda', 'Christian Jay', 'Chris', 'christian', 'miranda-kdt@global.kawasaki.com', 6, 2, '1983-08-25', 0, 1, '2007-08-16', 1, '0000-00-00'),
(243, 'Ramos', 'Sheena', 'Shin', 'serdina-kdt', 'serdina-kdt@global.kawasaki.com', 1, 47, '1985-09-03', 1, 1, '2007-12-03', 1, '0000-00-00'),
(252, 'Araneta', 'Rex', 'Rex', 'araneta-kdt', 'araneta-kdt@global.kawasaki.com', 5, 1, '1983-05-16', 0, 1, '2008-05-19', 1, '0000-00-00'),
(256, 'Sahagun', 'Michelle', 'Cheng', 'cheng', 'canalda-kdt@global.kawasaki.com', 6, 2, '1982-05-20', 1, 1, '2010-03-01', 0, '2022-12-31'),
(257, 'Onod', 'Abeel', 'Abel', 'abel', 'onod-kdt@global.kawasaki.com', 13, 2, '1986-07-07', 0, 1, '2010-03-01', 0, '2023-03-15'),
(259, 'Lucena', 'Rey', 'Rey', 'lucena-kdt', 'lucena-kdt@global.kawasaki.com', 6, 2, '1987-05-04', 0, 1, '2010-03-01', 1, '0000-00-00'),
(260, 'Verano', 'Ronald', 'RV', 'verano-kdt', 'verano-kdt@global.kawasaki.com', 9, 1, '1984-07-11', 0, 1, '2010-03-01', 1, '0000-00-00'),
(261, 'Villamor', 'Amor', 'Amor', 'villamor-kdt', 'villamor-kdt@global.kawasaki.com', 4, 2, '1984-09-15', 0, 1, '2010-03-01', 1, '0000-00-00'),
(262, 'Cantara', 'Mark Joel', 'Joel', 'cantara-kdt', 'cantara-kdt@global.kawasaki.com', 12, 2, '1984-01-20', 0, 0, '2010-03-01', 1, '0000-00-00'),
(263, 'Pascua', 'Henry', 'Henry', 'henry', 'pascua-kdt@global.kawasaki.com', 12, 1, '1984-06-19', 0, 1, '2010-03-01', 1, '0000-00-00'),
(264, 'Cular', 'Giulian Louis', 'Louis', 'louis', 'cular-kdt@global.kawasaki.com', 7, 2, '1986-06-21', 0, 0, '2010-03-01', 1, '0000-00-00'),
(265, 'Onod', 'Amalia', 'Amie', 'amie', 'nodalo-kdt@global.kawasaki.com', 8, 3, '1989-07-05', 1, 1, '2010-03-01', 0, '2023-03-15'),
(268, 'Sandoval', 'Dan Christian', 'Dan', 'sandoval', 'sandoval-kdt@global.kawasaki.com', 15, 2, '1985-10-06', 0, 0, '2010-06-01', 0, '2023-12-31'),
(270, 'Caringal', 'Gian Carlo', 'Carlo', 'caringal-kdt', 'caringal-kdt@global.kawasaki.com', 15, 1, '1985-12-15', 0, 1, '2010-06-01', 1, '0000-00-00'),
(272, 'Caveiro', 'Vincent', 'Vince', 'vince_resigned', 'caveiro-resigned@global.kawasaki.com', 15, 2, '1986-01-02', 0, 1, '2010-06-01', 0, '2023-03-31'),
(279, 'Panopio', 'Luisito', 'Louie', 'lpanopio', 'panopio-kdt@global.kawasaki.com', 12, 1, '1984-02-27', 0, 0, '2010-06-16', 1, '0000-00-00'),
(281, 'Cura', 'Leonard Ryan', 'Ryan', 'cura-kdt', 'cura-kdt@global.kawasaki.com', 4, 1, '1986-12-08', 0, 1, '2010-06-16', 1, '0000-00-00'),
(283, 'Cenizal', 'Maria Evangeline', 'Vangie', 'adami-kdt', 'adami-kdt@global.kawasaki.com', 20, 2, '1986-10-22', 1, 1, '2010-06-16', 1, '0000-00-00'),
(284, 'Cidro', 'James Eric', 'Jim', 'cidro-kdt', 'cidro-kdt@global.kawasaki.com', 20, 1, '1987-10-18', 0, 1, '2010-06-16', 1, '0000-00-00'),
(288, 'Godoy', 'Jhon Ray', 'Jhon', 'godoy-kdt', 'godoy-kdt@global.kawasaki.com', 20, 2, '1989-01-10', 0, 1, '2011-01-03', 1, '0000-00-00'),
(290, 'Ariap', 'Jomanil', 'Joma', 'joma', 'ariap-kdt@global.kawasaki.com', 7, 2, '1988-06-27', 0, 0, '2011-01-03', 1, '0000-00-00'),
(291, 'Llosala', 'Rowel', 'Wel', 'llosala-kdt', 'llosala-kdt@global.kawasaki.com', 4, 2, '1988-02-26', 0, 1, '2011-01-03', 1, '0000-00-00'),
(293, 'Dimaapi', 'Jasper', 'Jasper', 'jasper', 'dimaapi-kdt@global.kawasaki.com', 9, 2, '1989-05-16', 0, 0, '2011-01-03', 0, '2023-04-28'),
(295, 'Moreno', 'Rex', 'Rex', 'moreno-kdt', 'moreno-kdt@global.kawasaki.com', 12, 17, '1981-03-27', 0, 0, '2011-05-02', 1, '0000-00-00'),
(296, 'Macalalad', 'Joseph Michael', 'Joseph', 'macalalad', 'macalalad-kdt@global.kawasaki.com', 8, 17, '1981-02-16', 0, 1, '2011-05-02', 0, '2023-09-11'),
(299, 'Cano', 'Bryan Jay', 'Bryan', 'cano-kdt', 'cano-kdt@global.kawasaki.com', 18, 2, '1988-08-12', 0, 0, '2011-08-01', 1, '0000-00-00'),
(301, 'Lina', 'Dominador Joshua', 'Josh', 'lina-kdt', 'lina-kdt@global.kawasaki.com', 18, 2, '1989-07-03', 0, 0, '2011-08-01', 0, '2023-08-04'),
(302, 'Sampaga', 'Louie', 'Louie', 'sampaga-kdt', 'sampaga-kdt@global.kawasaki.com', 20, 2, '1987-12-02', 0, 1, '2012-01-16', 1, '0000-00-00'),
(304, 'Mundo', 'Daisy', 'Daisy', 'daisy', 'mundo-kdt@global.kawasaki.com', 8, 4, '1979-01-18', 1, 0, '2012-01-16', 0, '2021-11-01'),
(305, 'Escueta', 'Roderick', 'Eric', 'escueta-kdt', 'escueta-kdt@global.kawasaki.com', 15, 2, '1981-02-09', 0, 1, '2012-01-16', 1, '0000-00-00'),
(306, 'Belen', 'Kervin', 'Kervin', 'belen-kdt', 'belen-kdt@global.kawasaki.com', 15, 2, '1988-07-23', 0, 1, '2012-01-16', 1, '0000-00-00'),
(307, 'Ballon', 'Veronica', 'Vec', 'mendoza-kdt', 'mendoza-kdt@global.kawasaki.com', 10, 36, '1989-12-10', 1, 1, '2012-05-07', 1, '2025-02-10'),
(310, 'Tumaob', 'Ferdinand', 'Tommy', 'tumaob-kdt', 'tumaob-kdt@global.kawasaki.com', 13, 2, '1984-12-18', 0, 1, '2012-05-16', 1, '0000-00-00'),
(311, 'Penetrante Jr.', 'Jorge', 'JR', 'penetrante-kdt', 'penetrante-kdt@global.kawasaki.com', 18, 1, '1986-10-28', 0, 1, '2012-05-16', 1, '0000-00-00'),
(312, 'Lucas', 'Adelbert', 'Adel', 'adel', 'lucas-kdt@global.kawasaki.com', 18, 3, '1988-08-18', 0, 0, '2012-05-16', 1, '2024-08-14'),
(313, 'Agustines', 'Albert', 'Albert', 'agustines-kdt', 'agustines-kdt@global.kawasaki.com', 20, 3, '1987-07-24', 0, 0, '2012-05-16', 1, '0000-00-00'),
(314, 'Sangalang', 'Dickenson', 'Dick', 'dickenson', 'sangalang_d-kdt@global.kawasaki.com', 20, 2, '1983-11-17', 0, 1, '2012-05-16', 1, '0000-00-00'),
(316, 'Bautista Jr.', 'Celso', 'Ceejay', 'cj', 'bautista-kdt@global.kawasaki.com', 6, 3, '1991-05-23', 0, 0, '2013-02-16', 0, '2023-04-21'),
(320, 'Escueta Jr.', 'Juanito', 'Jeck', 'escueta_j-kdt', 'escueta_j-kdt@global.kawasaki.com', 20, 3, '1984-03-23', 0, 1, '2013-02-16', 1, '0000-00-00'),
(321, 'Bigtas', 'Keith Charm', 'Charm', 'charm', 'bigtas-kdt@global.kawasaki.com', 20, 3, '1985-03-26', 1, 0, '2013-02-16', 1, '0000-00-00'),
(322, 'Delarosa', 'Ester', 'Tets', 'delarosa-kdt', 'delarosa-kdt@global.kawasaki.com', 20, 2, '1987-02-28', 1, 0, '2013-02-16', 1, '0000-00-00'),
(323, 'Borlagon', 'Alvin Jan', 'Vin', 'vhin', 'borlagon-kdt@global.kawasaki.com', 18, 3, '1990-01-29', 0, 1, '2013-02-16', 1, '0000-00-00'),
(328, 'Calleja', 'Nomer', 'PIPOY', 'calleja-kdt', 'calleja-kdt@global.kawasaki.com', 18, 2, '1989-04-11', 0, 1, '2013-05-02', 1, '0000-00-00'),
(329, 'Marqueses', 'Jeff Edward', 'JEFF', 'marqueses_j-kdt', 'marqueses_j-kdt@global.kawasaki.com', 18, 3, '1988-11-01', 0, 1, '2013-05-02', 1, '0000-00-00'),
(330, 'Omiz', 'Gilbert Jason', 'GILBERT', 'omiz-kdt', 'omiz-kdt@global.kawasaki.com', 15, 3, '1991-04-03', 0, 0, '2013-08-01', 1, '0000-00-00'),
(332, 'De Guzman', 'Christopher', 'CHRIS', 'chrisdegz', 'deguzman-kdt@global.kawasaki.com', 8, 3, '1990-09-22', 0, 0, '2013-08-01', 0, '2021-11-01'),
(333, 'Mataum', 'Michael', 'KEL', 'mataum-kdt', 'mataum-kdt@global.kawasaki.com', 15, 3, '1989-06-30', 0, 0, '2013-08-01', 1, '2025-07-30'),
(334, 'Miranda', 'Julemir', 'JUN JUN', 'miranda_j-kdt', 'miranda_j-kdt@global.kawasaki.com', 8, 3, '1987-08-26', 0, 0, '2013-08-01', 1, '0000-00-00'),
(335, 'Ballon', 'Al John', 'AL JOHN', 'ballon-kdt', 'ballon-kdt@global.kawasaki.com', 12, 2, '1989-07-09', 0, 1, '2013-08-01', 1, '0000-00-00'),
(338, 'Bulan Jr.', 'Rosbelt', 'ROS', 'ross', 'bulan-kdt@global.kawasaki.com', 19, 3, '1990-03-14', 0, 0, '2013-10-01', 0, '2023-12-15'),
(344, 'Patron', 'Edilaine Marose', 'EM', 'patron-kdt', 'patron-kdt@global.kawasaki.com', 4, 3, '1992-01-24', 1, 0, '2014-01-02', 1, '0000-00-00'),
(345, 'Caunga', 'Rustum Oliver', 'RUSTUM', 'rocaunga', 'caunga-kdt@global.kawasaki.com', 7, 3, '1990-06-29', 0, 0, '2014-01-02', 1, '0000-00-00'),
(346, 'Bio', 'Marie Eleonor', 'MARIE', 'mirabel-kdt', 'mirabel-kdt@global.kawasaki.com', 20, 4, '1991-04-15', 1, 1, '2014-01-02', 1, '0000-00-00'),
(348, 'Tobias', 'Jeoffer', 'JEOFF', 'jltobias', 'tobias-kdt@global.kawasaki.com', 8, 4, '1990-07-24', 0, 0, '2014-04-01', 0, '2023-02-13'),
(350, 'Cajes', 'Joseph', 'DODONG', 'cajes-kdt', 'cajes-kdt@global.kawasaki.com', 7, 3, '1990-03-22', 0, 1, '2014-04-01', 1, '0000-00-00'),
(352, 'Ducay', 'John David', 'DAVID', 'ducay-kdt', 'ducay-kdt@global.kawasaki.com', 8, 3, '1989-08-28', 0, 1, '2014-04-01', 1, '0000-00-00'),
(353, 'Torio', 'Raffy', 'RAFFY', 'raffy', 'torio-kdt@global.kawasaki.com', 16, 22, '1989-02-02', 0, 0, '2014-04-01', 0, '2022-07-18'),
(355, 'De Jesus', 'Jommuel', 'JOM', 'jommuel', 'dejesus_j-kdt@global.kawasaki.com', 16, 22, '1992-01-29', 0, 1, '2014-04-01', 0, '2024-04-19'),
(356, 'Sumayop', 'Lian Marie', 'LIAN', 'cabradilla-kdt', 'cabradilla-kdt@global.kawasaki.com', 20, 3, '1991-02-07', 1, 1, '2014-04-01', 1, '0000-00-00'),
(357, 'Sanao', 'Jomari', 'JOM', 'sanao-kdt', 'sanao-kdt@global.kawasaki.com', 15, 2, '1992-11-19', 0, 1, '2014-06-02', 1, '0000-00-00'),
(358, 'Sanao', 'Roxanne May', 'XANXAN', 'velez-kdt', 'velez-kdt@global.kawasaki.com', 15, 3, '1991-03-11', 1, 1, '2014-06-02', 1, '0000-00-00'),
(364, 'Bitang', 'Kim Brian', 'Kim', 'Kimbrian', 'bitang-kdt@global.kawasaki.com', 8, 4, '1992-02-15', 1, 0, '2014-09-01', 0, '2021-11-01'),
(365, 'Magnaye', 'Lara Joy', 'LARA', 'lara', 'dimaculangan-kdt@global.kawasaki.com', 8, 4, '1991-09-17', 1, 1, '2014-09-01', 0, '2021-11-01'),
(367, 'Mendoza', 'Marcial', 'MARCY', 'mendoza_m-kdt', 'mendoza_m-kdt@global.kawasaki.com', 8, 2, '1990-05-24', 0, 1, '2014-09-01', 1, '0000-00-00'),
(370, 'Nopra', 'Charis Candy', 'CANDY', 'nopra-kdt', 'nopra-kdt@global.kawasaki.com', 10, 52, '1992-04-14', 1, 0, '2014-09-01', 1, '0000-00-00'),
(371, 'Cordova', 'Ysabel', 'Ysay', 'cordova-kdt', 'cordova-kdt@global.kawasaki.com', 12, 4, '1991-01-19', 1, 0, '2015-02-02', 1, '2025-02-10'),
(372, 'Frane', 'Gerald Christopher', 'Gerald', 'frane-kdt', 'frane-kdt@global.kawasaki.com', 20, 4, '1991-12-01', 0, 0, '2015-02-02', 1, '0000-00-00'),
(373, 'Sabarillo', 'Nelmar Bong', 'Bong', 'sabarillo-kdt', 'sabarillo-kdt@global.kawasaki.com', 7, 4, '1993-04-22', 0, 0, '2015-02-02', 1, '0000-00-00'),
(374, 'Fortus', 'Domini', 'Domini', 'domini_resign', 'fortus-kdt@global.kawasaki.com', 0, 4, '1993-07-12', 0, 0, '2015-02-02', 0, '2022-03-15'),
(376, 'Manaog', 'Karen Lorraine', 'Karen', 'karenv', 'vallestero-kdt@global.kawasaki.com', 20, 4, '1990-10-19', 1, 1, '2015-02-02', 1, '0000-00-00'),
(377, 'Guico', 'Aldrin', 'Gibo', 'guico-kdt', 'guico-kdt@global.kawasaki.com', 12, 3, '1991-08-11', 0, 0, '2015-02-02', 1, '0000-00-00'),
(378, 'Montalbo', 'Jennelyn', 'Jen', 'jhen', 'reyes_j-kdt@global.kawasaki.com', 12, 4, '1989-12-04', 1, 1, '2015-02-02', 0, '2021-11-01'),
(381, 'Flores', 'Angelo', 'Angelo', 'flores_a-kdt', 'flores_a-kdt@global.kawasaki.com', 12, 3, '1990-06-19', 0, 0, '2015-02-02', 1, '0000-00-00'),
(382, 'Marquez', 'Arvin David', 'Arvin', 'marquez-kdt', 'marquez-kdt@global.kawasaki.com', 5, 3, '1991-11-19', 0, 1, '2015-05-11', 1, '0000-00-00'),
(383, 'Bellen', 'Abegail', 'Abby', 'bellen-kdt', 'bellen-kdt@global.kawasaki.com', 5, 3, '1990-05-16', 1, 0, '2015-05-11', 1, '0000-00-00'),
(384, 'Gallardo', 'Al Shariff', 'Al', 'gallardo-kdt', 'gallardo-kdt@global.kawasaki.com', 3, 3, '1993-06-14', 0, 0, '2015-05-11', 1, '0000-00-00'),
(385, 'Perez', 'Jeremia', 'Mia', 'mia', 'perez_j-kdt@global.kawasaki.com', 5, 4, '1990-09-18', 1, 0, '2015-05-11', 0, '2024-01-20'),
(386, 'Tan', 'Dennis', 'Dennis', 'tan_d-kdt', 'tan_d-kdt@global.kawasaki.com', 11, 3, '1990-07-26', 0, 0, '2015-06-01', 1, '0000-00-00'),
(387, 'Balgoma', 'Ramil', 'Ram', 'balgoma-kdt', 'balgoma-kdt@global.kawasaki.com', 12, 3, '1992-11-17', 0, 0, '2015-06-01', 1, '0000-00-00'),
(388, 'Gupit', 'Bernico', 'Bernie', 'gupit-kdt', 'gupit-kdt@global.kawasaki.com', 12, 4, '1992-03-16', 0, 0, '2015-06-01', 1, '0000-00-00'),
(389, 'Miranda', 'Nikko', 'Nikko', 'nikkomiranda', 'miranda_n-kdt@global.kawasaki.com', 6, 3, '1992-03-16', 0, 0, '2015-06-01', 1, '0000-00-00'),
(390, 'Garcia', 'Christian Joseph', 'Anjo', 'garcia_c-kdt', 'garcia_c-kdt@global.kawasaki.com', 8, 3, '1991-02-10', 0, 1, '2015-06-01', 1, '0000-00-00'),
(391, 'Dalisay', 'Catherine', 'Cathy', 'austria-kdt', 'austria-kdt@global.kawasaki.com', 1, 48, '1984-09-30', 1, 1, '2015-08-06', 1, '0000-00-00'),
(393, 'De Sotto', 'Francis John', 'Kiko', 'francis', 'desotto-kdt@global.kawasaki.com', 12, 4, '1990-11-05', 0, 0, '2015-10-01', 0, '2022-08-01'),
(394, 'Banta', 'Leah Mariel', 'Leah', 'leah', 'banta-kdt@global.kawasaki.com', 12, 5, '1992-10-15', 1, 0, '2015-10-01', 0, '2022-05-08'),
(395, 'Villanueva', 'Gladys ', 'Gladys', 'Gladys', 'villanueva-kdt@global.kawasaki.com', 8, 5, '1993-02-26', 1, 0, '2015-10-01', 0, '2021-11-01'),
(396, 'Lacsa', 'John', 'John', 'lacsa-kdt', 'lacsa-kdt@global.kawasaki.com', 12, 4, '1993-01-16', 0, 0, '2015-10-01', 1, '0000-00-00'),
(397, 'Cantos', 'Reniel', 'Reniel', 'ren', 'cantos-kdt@global.kawasaki.com', 18, 5, '1993-03-11', 0, 0, '2015-10-01', 0, '2022-02-18'),
(398, 'Sta. Ana', 'Arnulfo', 'Arnold', 'kuyaarnold', '', 2, 45, '1965-03-02', 0, 1, '2016-04-01', 0, '2025-07-29'),
(401, 'Laureano', 'Miki Antonio', 'Miki', 'laureano_m-kdt', 'laureano_m-kdt@global.kawasaki.com', 8, 4, '1993-05-06', 0, 1, '2016-07-01', 1, '0000-00-00'),
(402, 'Ornido', 'Marvin John', 'Marvz', 'ornido-kdt', 'ornido-kdt@global.kawasaki.com', 18, 4, '1992-09-17', 0, 0, '2016-07-01', 1, '0000-00-00'),
(403, 'Mangaliman', 'Laarni', 'Lani', 'laarni', 'tumamao-kdt@global.kawasaki.com', 6, 4, '1993-09-04', 1, 1, '2016-07-01', 0, '2022-11-05'),
(404, 'Argente', 'Gene Owen', 'Owen', 'argente-kdt', 'argente-kdt@global.kawasaki.com', 8, 3, '1991-08-17', 0, 0, '2016-07-01', 1, '0000-00-00'),
(406, 'Tumbaga', 'Jefferson', 'Jep', 'tumbaga-kdt', 'tumbaga-kdt@global.kawasaki.com', 6, 4, '1993-07-22', 0, 1, '2016-07-01', 1, '0000-00-00'),
(407, 'Villatuya', 'Russel', 'Russel', 'rcvillatuya', 'villatuya-kdt@global.kawasaki.com', 20, 4, '1992-08-07', 0, 0, '2016-07-01', 1, '0000-00-00'),
(409, 'Dela Cruz', 'Earvin James', 'EJ', 'earvinjames', 'delacruz-kdt@global.kawasaki.com', 16, 25, '1993-04-03', 0, 0, '2016-07-01', 0, '2023-06-23'),
(410, 'Parra', 'Ely', 'Ely', 'parra-kdt', 'parra-kdt@global.kawasaki.com', 18, 4, '1994-07-11', 0, 0, '2017-03-01', 1, '0000-00-00'),
(411, 'Ilao', 'Marylou', 'Malou', 'malou', 'manalo-kdt@global.kawasaki.com', 8, 4, '1991-10-13', 1, 0, '2017-03-01', 1, '2025-02-21'),
(412, 'Rivera', 'Reenan', 'Reenan', 'rivera-kdt', 'rivera-kdt@global.kawasaki.com', 8, 4, '1992-10-01', 0, 0, '2017-03-01', 1, '0000-00-00'),
(413, 'Buzeta', 'Wendy', 'Wendy', 'anorico-kdt', 'anorico-kdt@global.kawasaki.com', 12, 4, '1991-01-22', 1, 0, '2017-03-01', 1, '0000-00-00'),
(414, 'Antonio', 'Wilhelm Dennis', 'Lem', 'antonio-kdt', 'antonio-kdt@global.kawasaki.com', 8, 4, '1994-08-05', 0, 0, '2017-03-01', 1, '0000-00-00'),
(415, 'Astrera', 'Philip Jhon', 'Philip', 'philipj', 'astrera-kdt@global.kawasaki.com', 8, 4, '1995-11-23', 0, 0, '2017-03-01', 0, '2023-07-28'),
(416, 'Floresca', 'Gene Philip', 'Gene', 'floresca-kdt', 'floresca-kdt@global.kawasaki.com', 9, 4, '1992-11-20', 0, 0, '2017-03-01', 1, '0000-00-00'),
(417, 'Binay-an', 'Daryl', 'Daryl', 'binayan-kdt', 'binayan-kdt@global.kawasaki.com', 12, 4, '1994-10-24', 0, 0, '2017-03-01', 1, '0000-00-00'),
(419, 'Mesias', 'Meriam', 'Yamie', 'mesias-kdt', 'mesias-kdt@global.kawasaki.com', 2, 42, '1985-08-15', 1, 0, '2017-09-04', 1, '0000-00-00'),
(420, 'Benedicto', 'Alvin', 'Vin', 'benedicto-kdt', 'benedicto-kdt@global.kawasaki.com', 8, 5, '1994-10-20', 0, 0, '2018-07-02', 1, '0000-00-00'),
(421, 'Nazar', 'John Jacob', 'Jacob', 'jjnazar', 'nazar-kdt@global.kawasaki.com', 12, 5, '1996-01-28', 0, 0, '2018-07-02', 1, '2025-01-13'),
(422, 'Pimentel', 'Lucky Boy', 'Lux', 'lux', 'pimentel-kdt@global.kawasaki.com', 8, 5, '1996-10-20', 0, 0, '2018-07-02', 0, '2023-06-16'),
(423, 'Caro', 'Renson', 'Renson', 'caro-kdt', 'caro-kdt@global.kawasaki.com', 8, 5, '1995-01-15', 0, 0, '2018-07-02', 1, '0000-00-00'),
(424, 'Perez', 'Renzel', 'Renzel', 'perez_r-kdt', 'perez_r-kdt@global.kawasaki.com', 12, 5, '1994-09-09', 0, 0, '2018-07-02', 1, '0000-00-00'),
(425, 'Belen', 'Marvin', 'Marvin', 'belen_m-kdt', 'belen_m-kdt@global.kawasaki.com', 8, 5, '1994-09-22', 0, 0, '2018-07-02', 1, '0000-00-00'),
(426, 'Tuazon', 'King Louis', 'King', 'tuazon-kdt', 'tuazon-kdt@global.kawasaki.com', 18, 4, '1992-04-14', 0, 0, '2018-07-02', 1, '0000-00-00'),
(427, 'Fernandez', 'Julius', 'Julius', 'fernandez-kdt', 'fernandez-kdt@global.kawasaki.com', 12, 4, '1994-01-02', 0, 0, '2018-07-02', 1, '0000-00-00'),
(428, 'Velasco', 'Vryan', 'Vryan', 'velasco-kdt', 'velasco-kdt@global.kawasaki.com', 8, 4, '1993-10-05', 0, 0, '2018-07-02', 1, '0000-00-00'),
(429, 'Moralita', 'Denmark', 'Denmark', 'moralita-kdt', 'moralita-kdt@global.kawasaki.com', 8, 4, '1994-09-10', 0, 0, '2018-07-02', 1, '0000-00-00'),
(430, 'Lodevico', 'Zendy Grace', 'Zendy', 'ucol-kdt', 'ucol-kdt@global.kawasaki.com', 8, 4, '1995-08-13', 1, 1, '2018-07-02', 1, '0000-00-00'),
(432, 'Macaraan', 'Christian', 'Christian', 'macaraan-kdt', 'macaraan-kdt@global.kawasaki.com', 8, 5, '1993-12-24', 0, 0, '2018-07-02', 1, '0000-00-00'),
(433, 'Ramos', 'Alyssa', 'Alyssa', 'ramos-kdt', 'ramos-kdt@global.kawasaki.com', 12, 4, '1995-09-04', 1, 0, '2018-07-02', 1, '0000-00-00'),
(434, 'Pilis', 'Vilmer', 'Vilmer', 'pilis-kdt', 'pilis-kdt@global.kawasaki.com', 8, 4, '1993-09-12', 0, 0, '2018-07-02', 1, '0000-00-00'),
(435, 'Guico', 'Kerstin Paula', 'Kerstin', 'kbasibas', 'basibas-kdt@global.kawasaki.com', 8, 5, '1995-02-03', 1, 1, '2018-07-02', 1, '0000-00-00'),
(436, 'Viñas', 'Eugene', 'Eugene', 'vinas-kdt', 'vinas-kdt@global.kawasaki.com', 8, 5, '1994-03-15', 0, 0, '2018-07-02', 1, '0000-00-00'),
(437, 'Callores', 'Dick Francis', 'Dick', 'callores-kdt', 'callores-kdt@global.kawasaki.com', 8, 5, '1993-10-27', 0, 0, '2018-07-02', 1, '0000-00-00'),
(438, 'Reyes', 'Juan Carlos', 'JC', 'reyes_jc-kdt', 'reyes_jc-kdt@global.kawasaki.com', 8, 5, '1994-09-19', 0, 0, '2018-07-02', 1, '0000-00-00'),
(439, 'Arroyo', 'Micah Camille', 'Micah', 'arroyo-kdt', 'arroyo-kdt@global.kawasaki.com', 10, 30, '1997-09-13', 1, 0, '2018-07-02', 1, '0000-00-00'),
(440, 'Panginbayan', 'Rochelle', 'Chelle', 'panginbayan-kdt', 'panginbayan-kdt@global.kawasaki.com', 10, 30, '1997-07-20', 1, 0, '2018-07-02', 1, '0000-00-00'),
(444, 'Tobias', 'Kenneth', 'Ken', 'tobias_k-kdt', 'tobias_k-kdt@global.kawasaki.com', 5, 4, '1996-01-26', 0, 0, '2018-10-01', 1, '0000-00-00'),
(445, 'Honrado', 'Ruth Anne', 'Ruth', 'honrado-kdt', 'honrado-kdt@global.kawasaki.com', 3, 5, '1992-09-04', 1, 0, '2018-10-01', 1, '0000-00-00'),
(446, 'Takenaka', 'Yukihiro', 'Takenaka-san', 'takenaka_yu', 'takenaka_yu@global.kawasaki.com', 2, 29, '1965-11-25', 0, 0, '2016-11-25', 1, '0000-00-00'),
(447, 'Medrano', 'Marco', 'Marco', 'medrano-kdt', 'medrano-kdt@global.kawasaki.com', 15, 5, '1997-10-04', 0, 0, '2019-07-01', 1, '0000-00-00'),
(448, 'Buston', 'Cherry Mae ', 'Che', 'che', 'soliven-kdt@global.kawasaki.com', 15, 5, '1996-03-08', 1, 0, '2019-07-01', 1, '2024-10-30'),
(449, 'Viado', 'Meyrvin', 'Meyrvin', 'meyrvin', 'viado-kdt@global.kawasaki.com', 15, 5, '1996-05-11', 0, 0, '2019-07-01', 0, '2024-01-01'),
(450, 'casem', 'Kimberly', 'Kim', 'casem-kdt', 'casem-kdt@global.kawasaki.com', 15, 5, '1995-04-19', 1, 0, '2019-07-01', 1, '0000-00-00'),
(451, 'Guiao ', 'Neil Stephen ', 'Neil', 'guiao', 'guiao-kdt@global.kawasaki.com', 15, 7, '1995-10-22', 0, 0, '2019-07-01', 0, '2022-03-23'),
(452, 'Nigos', 'Scottee Jairus', 'Scottee', 'scotty', 'nigos-kdt@global.kawasaki.com', 3, 5, '1997-10-28', 0, 0, '2019-07-01', 0, '2024-05-15'),
(454, 'Armas', 'Kenn John', 'Ken', 'armas-kdt', 'armas-kdt@global.kawasaki.com', 18, 5, '1994-06-01', 0, 0, '2019-07-01', 1, '0000-00-00'),
(455, 'Manalo', 'Vincen', 'Vincen', 'manalo_v-kdt', 'manalo_v-kdt@global.kawasaki.com', 8, 5, '1996-06-01', 0, 0, '2019-07-01', 1, '0000-00-00'),
(456, 'Rivera', 'Max Vincent', 'Max', 'rivera_m-kdt', 'rivera_m-kdt@global.kawasaki.com', 8, 5, '1995-05-14', 0, 0, '2019-07-01', 0, '2023-08-19'),
(459, 'Barlolong', 'Rennel Mae', 'Rennel', 'cutaran-kdt', 'cutaran-kdt@global.kawasaki.com', 8, 5, '1997-12-11', 1, 1, '2021-07-12', 1, '0000-00-00'),
(460, 'Berongoy', 'Eurjhon', 'Eur', 'berongoy-kdt', 'berongoy-kdt@global.kawasaki.com', 8, 6, '1998-08-19', 0, 0, '2021-07-12', 1, '0000-00-00'),
(461, 'Bayquen', 'Hannah Millace', 'Hannah', 'bayquen-kdt', 'bayquen-kdt@global.kawasaki.com', 8, 6, '1997-05-20', 1, 0, '2021-07-12', 1, '0000-00-00'),
(462, 'Santos', 'Luize Nicole', 'Lui', 'santos-kdt', 'santos-kdt@global.kawasaki.com', 8, 6, '1998-10-08', 0, 0, '2021-07-12', 1, '0000-00-00'),
(463, 'Reyes', 'Rizchelle', 'Riz', 'reyes_r-kdt', 'reyes_r-kdt@global.kawasaki.com', 8, 6, '1999-11-14', 1, 0, '2021-07-12', 1, '0000-00-00'),
(464, 'Coquia ', 'Joshua Mari ', 'Joshua', 'coquia-kdt', 'coquia-kdt@global.kawasaki.com', 24, 24, '1999-08-15', 0, 0, '2021-07-12', 1, '0000-00-00'),
(465, 'Petate', 'Felix Edwin', 'Felix', 'felix', 'petate-kdt@global.kawasaki.com', 16, 24, '1992-03-14', 0, 0, '2021-07-12', 0, '2024-03-06'),
(466, 'Aganan', 'Alvin John', 'Alvin', 'aganan-kdt', 'aganan-kdt@global.kawasaki.com', 16, 23, '1998-07-28', 0, 0, '2021-07-12', 0, '2023-08-10'),
(467, 'Camunggol', 'Aldrin Jerick', 'Aldrin', 'camunggol-kdt', 'camunggol-kdt@global.kawasaki.com', 18, 6, '1999-04-01', 0, 0, '2021-07-12', 1, '0000-00-00'),
(468, 'Gamez', 'Aaron Godfrey', 'Ron', 'gamez-kdt', 'gamez-kdt@global.kawasaki.com', 13, 6, '1998-08-08', 0, 0, '2021-07-12', 1, '0000-00-00'),
(469, 'Arabilla Jr.', 'Joel', 'joel', 'arabilla-kdt', 'arabilla-kdt@global.kawasaki.com', 13, 7, '1999-08-12', 0, 0, '2022-07-01', 1, '0000-00-00'),
(470, 'Leguin', 'Aries', 'Aries', 'leguin-kdt', 'leguin-kdt@global.kawasaki.com', 12, 7, '1999-05-11', 0, 0, '2022-07-01', 1, '0000-00-00'),
(471, 'Chavez', 'Mark Rian', 'Mark Rian', 'chavez-kdt', 'chavez-kdt@global.kawasaki.com', 15, 6, '1997-09-15', 0, 0, '2022-07-01', 1, '0000-00-00'),
(472, 'Lazaro', 'Nelson', 'nelson', 'lazaro_n-kdt', 'lazaro_n-kdt@global.kawasaki.com', 6, 6, '1993-07-14', 0, 0, '2022-07-01', 1, '0000-00-00'),
(473, 'Domaoal', 'Kenneth', 'Kenneth', 'domaoal-kdt', 'domaoal-kdt@global.kawasaki.com', 8, 6, '1998-07-28', 0, 0, '2022-07-01', 1, '0000-00-00'),
(474, 'Segovia', 'Nicole Alysson', 'Nicole ', 'segovia-kdt', 'segovia-kdt@global.kawasaki.com', 8, 7, '1996-01-23', 1, 0, '2022-07-01', 1, '2024-08-23'),
(475, 'Del Rosario', 'Jay-R', 'Jay-R', 'delrosario-kdt', 'delrosario-kdt@global.kawasaki.com', 15, 6, '1999-04-27', 0, 0, '2022-07-01', 1, '0000-00-00'),
(477, 'Callanta', 'Von Joemar', 'Von Joemar', 'callanta-kdt', 'callanta-kdt@global.kawasaki.com', 15, 7, '1997-07-12', 0, 0, '2022-07-01', 1, '0000-00-00'),
(478, 'Bamba', 'Angelo Justin', 'Angelo Justin', 'bamba-kdt', 'bamba-kdt@global.kawasaki.com', 12, 7, '1996-09-27', 0, 0, '2022-07-01', 1, '0000-00-00'),
(479, 'Abella', 'Sean Vinze', 'Sean Vinze', 'abella-kdt', 'abella-kdt@global.kawasaki.com', 8, 7, '1998-12-03', 0, 0, '2022-07-01', 1, '0000-00-00'),
(480, 'Tamayo', 'Christian Mari', 'Christian Mari', 'tamayo-kdt', 'tamayo-kdt@global.kawasaki.com', 18, 7, '1998-12-29', 0, 0, '2022-07-01', 1, '0000-00-00'),
(481, 'Tan Pian', 'John Meynard', 'John Meynard', 'tanpian-kdt', 'tanpian-kdt@global.kawasaki.com', 8, 7, '1998-10-05', 0, 0, '2022-07-01', 1, '0000-00-00'),
(482, 'Ramirez', 'Xavier Dwight', 'Xavier Dwight', 'ramirez-kdt', 'ramirez-kdt@global.kawasaki.com', 15, 6, '1998-12-17', 0, 0, '2022-07-01', 1, '2025-03-07'),
(483, 'Bautista', 'Anne Wilyn', 'Anne Wilyn', 'bautista_anne-kdt', 'bautista_anne-kdt@global.kawasaki.com', 8, 7, '1998-12-17', 1, 0, '2022-07-01', 1, '0000-00-00'),
(484, 'Jonson', 'Edgar Joseph', 'Edgar', 'jonson-kdt', 'jonson-kdt@global.kawasaki.com', 20, 6, '1997-08-31', 0, 0, '2022-07-01', 1, '0000-00-00'),
(485, 'Pangilinan', 'Seanne Kyle', 'Seanne Kyle', 'pangilinan-kdt', 'pangilinan-kdt@global.kawasaki.com', 5, 6, '1999-09-09', 0, 0, '2022-07-01', 1, '0000-00-00'),
(486, 'Maximo', 'Carl Rey', 'Carl Rey', 'maximo-kdt', 'maximo-kdt@global.kawasaki.com', 4, 7, '1994-11-16', 0, 0, '2022-07-01', 1, '0000-00-00'),
(487, 'Medrano', 'Collene Keith', 'Collene', 'medrano_c-kdt', 'medrano_c-kdt@global.kawasaki.com', 24, 37, '1999-01-13', 1, 0, '2022-08-15', 1, '0000-00-00'),
(488, 'Gulam', 'Glenda Ann', 'Glenda', 'Glenda', 'gulam-kdt@global.kawasaki.com', 16, 20, '2000-03-27', 1, 0, '2022-08-15', 0, '2023-01-31'),
(489, 'Fortus', 'Domini', 'domini', 'fortus_d-kdt', 'fortus_d-kdt@global.kawasaki.com', 12, 3, '1993-07-12', 0, 0, '2023-01-16', 1, '0000-00-00'),
(490, 'Usal', 'Ryan Christopher', 'Ryan', 'usal-kdt', 'usal-kdt@global.kawasaki.com', 12, 7, '1997-11-18', 0, 0, '2023-03-01', 1, '0000-00-00'),
(491, 'Villamil', 'Ronald Louie', 'Ronal', 'villamil-kdt', 'villamil-kdt@global.kawasaki.com', 12, 7, '1998-12-15', 0, 0, '2023-03-01', 1, '0000-00-00'),
(492, 'Villaruel', 'Jayron', 'Jayron', 'villaruel-kdt', 'villaruel-kdt@global.kawasaki.com', 12, 7, '1996-06-04', 0, 0, '2023-03-01', 1, '0000-00-00'),
(493, 'Bedonia', 'Bryan James', 'Bryan', 'bedonia-kdt', 'bedonia-kdt@global.kawasaki.com', 12, 7, '1998-03-12', 0, 0, '2023-03-01', 1, '0000-00-00'),
(494, 'Montaniel', 'John Carlos', 'John ', 'montaniel-kdt', 'montaniel-kdt@global.kawasaki.com', 12, 7, '1998-12-04', 0, 0, '2023-03-01', 1, '0000-00-00'),
(495, 'Rodriguez', 'Nicole', 'Nicole', 'rodriguez-kdt', 'rodriguez-kdt@global.kawasaki.com', 12, 7, '1998-12-04', 1, 0, '2023-03-01', 1, '0000-00-00'),
(496, 'Tana', 'Marc Jullian', 'Marc', 'tana-kdt', 'tana-kdt@global.kawasaki.com', 12, 7, '1999-01-25', 0, 0, '2023-03-01', 1, '0000-00-00'),
(497, 'Alagar', 'Mark Joshua', 'Mark Joshua', 'mj', 'mj@global.kawasaki.com', 2, 40, '2003-04-14', 0, 0, '2023-05-22', 0, '2023-08-11'),
(498, 'Cerdan Jr.', 'Tagumpay', 'tagi', 'cerdan_t-kdt', 'cerdan_t-kdt@global.kawasaki.com', 16, 21, '1984-08-07', 0, 1, '2023-06-05', 1, '0000-00-00'),
(499, 'Pagola', 'James Bryan', 'james', 'pagola-kdt', 'pagola-kdt@global.kawasaki.com', 7, 7, '2000-08-03', 0, 0, '2023-07-03', 1, '0000-00-00'),
(500, 'Recto', 'Joshua John', 'joshua', 'recto-kdt', 'recto-kdt@global.kawasaki.com', 8, 7, '1997-09-06', 0, 0, '2023-07-03', 1, '0000-00-00'),
(501, 'Mamalayan', 'John Carlo', 'john', 'mamalayan-kdt', 'mamalayan-kdt@global.kawasaki.com', 15, 7, '1999-10-03', 0, 0, '2023-07-03', 1, '0000-00-00'),
(502, 'Baloloy', 'Danadel', 'danadel', 'baloloy-kdt', 'baloloy-kdt@global.kawasaki.com', 8, 7, '2000-03-12', 1, 0, '2023-07-03', 1, '0000-00-00'),
(503, 'Lavarias', 'Vincent Adrian', 'vincent', 'lavarias-kdt', 'lavarias-kdt@global.kawasaki.com', 18, 7, '1999-10-03', 0, 0, '2023-07-03', 1, '0000-00-00'),
(504, 'Cayabyab', 'Shield', 'shield', 'cayabyab-kdt', 'cayabyab-kdt@global.kawasaki.com', 4, 7, '2000-07-15', 0, 0, '2023-07-03', 1, '0000-00-00'),
(505, 'Bernabe', 'Edison James', 'edison', 'bernabe-kdt', 'bernabe-kdt@global.kawasaki.com', 6, 7, '1998-05-09', 0, 0, '2023-07-03', 1, '0000-00-00'),
(506, 'Adona', 'Julius Ian', 'julius', 'adona-kdt', 'adona-kdt@global.kawasaki.com', 4, 7, '1998-06-18', 0, 0, '2023-07-03', 1, '0000-00-00'),
(507, 'Dimaculangan', 'Wawini', 'wawini', 'dimaculangan_w-kdt', 'dimaculangan_w-kdt@global.kawasaki.com', 13, 7, '1999-12-18', 0, 0, '2023-07-03', 1, '0000-00-00'),
(508, 'Cumara', 'Christian Jasper', 'jasper', 'cumara-kdt', 'cumara-kdt@global.kawasaki.com', 15, 7, '2000-07-24', 0, 0, '2023-07-03', 1, '0000-00-00'),
(509, 'Oruga', 'Arjay', 'arjay', 'oruga-kdt', 'oruga-kdt@global.kawasaki.com', 20, 7, '1999-11-19', 0, 0, '2023-07-03', 1, '0000-00-00'),
(510, 'Hernandez', 'Dexmel Mico', 'dexmel', 'hernandez-kdt', 'hernandez-kdt@global.kawasaki.com', 24, 37, '1998-11-23', 0, 0, '2023-07-03', 1, '0000-00-00'),
(511, 'Apolinario', 'Timothy Jay', 'timothy', 'apolinario-kdt', 'apolinario-kdt@global.kawasaki.com', 16, 37, '2000-08-04', 0, 0, '2023-07-03', 0, '2024-07-19'),
(513, 'Alano', 'Adrian William', 'adrian', 'alano-kdt', 'alano-kdt@global.kawasaki.com', 16, 37, '1996-02-19', 0, 0, '2023-07-03', 0, '2024-10-02'),
(514, 'Taub', 'Prencess Loraine', 'Loraine', 'taub-kdt', 'taub-kdt@global.kawasaki.com', 10, 34, '2000-05-17', 1, 0, '2023-09-04', 1, '0000-00-00'),
(515, 'Bulan', 'Rosbelt Jr.', 'Ross', 'bulan_r-kdt', 'bulan_r-kdt@global.kawasaki.com', 4, 2, '1990-03-14', 0, 0, '2024-04-29', 1, '0000-00-00'),
(516, 'Pimentel', 'Lucky Boy', 'lux', 'pimentel_l-kdt', 'pimentel_l-kdt@global.kawasaki.com', 8, 4, '1995-10-20', 0, 0, '2024-05-06', 1, '0000-00-00'),
(517, 'Sangalang', 'Marielle', 'ice', 'sangalang_m-kdt', 'sangalang_m-kdt@global.kawasaki.com', 16, 37, '2000-04-04', 1, 0, '2024-06-03', 1, '0000-00-00'),
(518, 'Reyes', 'Dave', 'dabe', 'reyes_d-kdt', 'reyes_d-kdt@global.kawasaki.com', 16, 37, '2001-03-29', 0, 0, '2024-06-03', 1, '0000-00-00'),
(520, 'Herrera', 'Rhanzces Julia', 'Rhanzces', 'herrera-kdt', 'herrera-kdt@global.kawasaki.com', 16, 37, '2001-06-23', 1, 0, '2024-06-03', 1, '0000-00-00'),
(521, 'Cabiso', 'Sean Patrick', 'Sean', 'cabiso-kdt', 'cabiso-kdt@global.kawasaki.com', 16, 37, '2001-09-01', 0, 0, '2024-06-03', 1, '0000-00-00'),
(522, 'Nepales', 'Kristine Jewel', 'kristine', 'nepales-kdt', 'nepales-kdt@global.kawasaki.com', 5, 8, '2000-11-25', 1, 0, '2024-07-01', 1, '0000-00-00'),
(523, 'Sakilan', 'Anizza Marie', 'anizza', 'sakilan-kdt', 'sakilan-kdt@global.kawasaki.com', 5, 8, '1999-04-27', 1, 0, '2024-07-01', 1, '0000-00-00'),
(524, 'Sampaga', 'Mark Froilan', 'froilan', 'sampaga_m-kdt', 'sampaga_m-kdt@global.kawasaki.com', 20, 8, '2000-04-13', 0, 0, '2024-07-01', 1, '0000-00-00'),
(525, 'Dacillo', 'Nicole', 'nicole', 'dacillo-kdt', 'dacillo-kdt@global.kawasaki.com', 20, 8, '1999-11-16', 1, 0, '2024-07-01', 1, '0000-00-00'),
(526, 'Bayle', 'Jayvie', 'jayvie', 'bayle-kdt', 'bayle-kdt@global.kawasaki.com', 20, 8, '2000-01-27', 0, 0, '2024-07-01', 1, '0000-00-00'),
(527, 'Gao', 'Christian', 'christian', 'gao-kdt', 'gao-kdt@global.kawasaki.com', 8, 8, '1996-12-21', 0, 0, '2024-07-01', 1, '0000-00-00'),
(528, 'King', 'Jehann Miguel', 'miguel', 'king-kdt', 'king-kdt@global.kawasaki.com', 12, 8, '2000-03-15', 0, 0, '2024-07-01', 1, '2024-10-16'),
(529, 'Masilang', 'Raymond', 'raymond', 'masilang-kdt', 'masilang-kdt@global.kawasaki.com', 15, 3, '1991-09-05', 0, 0, '2024-07-01', 1, '0000-00-00'),
(530, 'Taer', 'Jancarl', 'jancarl', 'taer-kdt', 'taer-kdt@global.kawasaki.com', 12, 8, '2001-08-25', 0, 0, '2024-07-01', 1, '0000-00-00'),
(531, 'Lanuza', 'Khareen', 'kring', 'lanuza_k-kdt', 'lanuza_k-kdt@global.kawasaki.com', 8, 3, '1983-07-10', 1, 0, '2024-07-01', 1, '0000-00-00'),
(532, 'Regio', 'Clim Jobert', 'clim', 'regio-kdt', 'regio-kdt@global.kawasaki.com', 12, 8, '2001-01-10', 0, 0, '2024-07-01', 1, '0000-00-00'),
(533, 'Reyes', 'Aaron Emmanuel', 'emmanuel', 'reyes_a-kdt', 'reyes_a-kdt@global.kawasaki.com', 12, 8, '2000-09-13', 0, 0, '2024-07-01', 1, '2025-03-13'),
(534, 'Pilante', 'Antoinne Kobe', 'kobe', 'pilante-kdt', 'pilante-kdt@global.kawasaki.com', 8, 8, '2001-03-29', 0, 0, '2024-07-01', 1, '0000-00-00'),
(535, 'Santos', 'Peter Simon', 'simon', 'santos_p-kdt', 'santos_p-kdt@global.kawasaki.com', 12, 8, '2000-08-31', 0, 0, '2024-07-01', 1, '0000-00-00'),
(536, 'Tan', 'Robert Jordan', 'jordan', 'tan_r-kdt', 'tan_r-kdt@global.kawasaki.com', 18, 8, '2000-08-11', 0, 0, '2024-07-01', 1, '2025-02-14'),
(537, 'Dazo', 'Joven Luis', 'luis', 'dazo-kdt', 'dazo-kdt@global.kawasaki.com', 4, 8, '2000-07-17', 0, 0, '2024-07-01', 1, '0000-00-00'),
(538, 'Cablay', 'Zeph Agustine', 'zeph', 'cablay-kdt', 'cablay-kdt@global.kawasaki.com', 15, 8, '1999-09-01', 0, 0, '2024-07-01', 1, '0000-00-00'),
(539, 'Fajardo', 'Hans Alexander', 'Hans', 'fajardo-kdt', 'fajardo-kdt@global.kawasaki.com', 16, 20, '2001-09-01', 0, 0, '2024-09-30', 1, NULL),
(540, 'Pactol', 'Vonn Jezreel', 'Vonn', 'pactol-kdt', 'pactol-kdt@global.kawasaki.com', 16, 20, '2002-01-28', 0, 0, '2024-09-30', 1, NULL),
(541, 'Tañedo', 'Joshua Roi', 'Joshua', 'tanedo-kdt', 'tanedo-kdt@global.kawasaki.com', 16, 20, '1998-06-07', 0, 0, '2024-09-30', 1, NULL),
(542, 'Rivas', 'Edric Jay', 'Edric ', 'rivas-kdt', 'rivas-kdt@global.kawasaki.com', 16, 20, '2000-11-17', 0, 0, '2024-09-30', 1, NULL),
(543, 'Dela Vega', 'Joriz Anne', 'Joriz', 'delavega-kdt', 'delavega-kdt@global.kawasaki.com', 16, 20, '2003-05-06', 1, 0, '2024-09-30', 1, NULL),
(544, 'Briton', 'Josh Gabriel', 'Gab', 'briton-kdt', 'briton-kdt@global.kawasaki.com', 10, 54, '2000-03-16', 0, 0, '2024-09-30', 1, NULL),
(545, 'Kondo', 'Yuma', 'kondo_yuuma', 'kondo_yuuma', 'kondo_yuuma@global.kawasaki.com', 2, 17, '1992-08-16', 0, 1, '2025-01-01', 1, NULL),
(546, 'Claudio', 'Agusto', 'Kuya August', 'nopc', 'noemail@global.kawasaki.com', 2, 40, '1985-08-21', 0, 1, '2025-01-01', 1, NULL),
(547, 'Mariano jR', 'Jonathan', 'jr', 'mariano-kdt', 'mariano-kdt@global.kawasaki.com', 6, 8, '2001-02-06', 1, 0, '2025-07-01', 1, NULL),
(548, 'Montemayor', 'Darren Jordan', 'Darren', 'montemayor-kdt', 'montemayor-kdt@global.kawasaki.com', 13, 8, '2002-08-09', 0, 0, '2025-07-01', 1, NULL),
(549, 'Barbosa', 'Joshua Kyle', 'Joshua', 'barbosa-kdt', 'barbosa-kdt@global.kawasaki.com', 7, 8, '2001-02-28', 0, 0, '2025-07-01', 1, NULL),
(550, 'Galisim', 'Kian', 'Kian', 'galisim-kdt', 'galisim-kdt@global.kawasaki.com', 12, 8, '2001-08-21', 0, 0, '2025-07-01', 1, NULL),
(551, 'Ochava', 'Jocel Ivan', 'Ivan', 'ochava-kdt', 'ochava-kdt@global.kawasaki.com', 12, 8, '2001-08-14', 0, 0, '2025-07-01', 1, NULL),
(552, 'Ramos', 'Kiel Marko', 'Kiel', 'ramos_k-kdt', 'ramos_k-kdt@global.kawasaki.com', 12, 8, '2001-11-23', 0, 0, '2025-07-01', 1, NULL),
(553, 'Gercan', 'MIkaela', 'Elllie', 'gercan-kdt', 'gercan-kdt@global.kawasaki.com', 12, 8, '2002-07-30', 1, 0, '2025-07-01', 1, NULL),
(554, 'Masangkay', 'Alejo', 'Alejo', 'masangkay-kdt', 'masangkay-kdt@global.kawasaki.com', 12, 8, '2001-08-14', 0, 0, '2025-07-01', 1, NULL),
(555, 'Valenzuela', 'Aaron', 'Aaron', 'valenzuela-kdt', 'valenzuela-kdt@global.kawasaki.com', 12, 8, '2000-05-25', 0, 0, '2025-07-01', 1, NULL),
(556, 'Rosales', 'Jose Marianito/Ma.Dominic', 'Nico', 'rosales-kdt', 'rosales-kdt@global.kawasaki.com', 8, 8, '2002-03-25', 0, 0, '2025-07-01', 1, NULL),
(557, 'Cruz', 'Adrian', 'Adrian', 'cruz-kdt', 'cruz-kdt@global.kawasaki.com', 8, 8, '2000-11-20', 0, 0, '2025-07-01', 1, NULL),
(558, 'Ortiz', 'Marl Aeron', 'Aeron', 'ortiz_m-kdt', 'ortiz_m-kdt@global.kawasaki.com', 8, 8, '2001-12-11', 0, 0, '2025-07-01', 1, NULL),
(559, 'Jubay', 'Andrea', 'Andrea', 'jubay-kdt', 'jubay-kdt@global.kawasaki.com', 8, 8, '2000-09-15', 1, 0, '2025-07-01', 1, '2025-06-26'),
(560, 'Viaje', 'Jed Millard', 'Jed', 'viaje-kdt', 'viaje-kdt@global.kawasaki.com', 18, 8, '2002-01-16', 0, 0, '2025-07-01', 1, NULL),
(561, 'Villamor', 'Lawrence Benedict', 'Kong', 'villamor_l-kdt', 'villamor_l-kdt@global.kawasaki.com', 18, 8, '2000-06-24', 0, 0, '2025-07-01', 1, NULL),
(562, 'De sagun', 'Noreen', 'Noreen', 'desagun-kdt', 'desagun-kdt@global.kawasaki.com', 15, 8, '2001-02-27', 1, 0, '2025-07-01', 1, NULL),
(563, 'Dig', 'Jade Jowinson', 'Dig', 'dig-kdt', 'dig-kdt@global.kawasaki.com', 15, 8, '2001-10-08', 0, 0, '2025-07-01', 1, NULL),
(564, 'Soleta', 'Eyljhem', 'Jigs', 'soleta-kdt', 'soleta-kdt@global.kawasaki.com', 15, 8, '2001-09-06', 0, 0, '2025-07-01', 1, NULL),
(565, 'Peralta', 'John Kenneth', 'Kenneth', 'peralta-kdt', 'peralta-kdt@global.kawasaki.com', 15, 8, '2002-02-11', 0, 0, '2025-07-01', 1, NULL),
(566, 'Sumagui', 'Scott Aaron', 'Aaron', 'sumagui-kdt', 'sumagui-kdt@global.kawasaki.com', 15, 8, '2001-12-02', 0, 0, '2025-07-01', 1, NULL),
(567, 'Caluntad', 'Ronn Angelo', 'Ronn', 'caluntad-kdt', 'caluntad-kdt@global.kawasaki.com', 15, 8, '2000-07-15', 0, 0, '2025-07-01', 1, NULL),
(568, 'Estoquia', 'Rose Ann', 'Rose', 'estoquia-kdt', 'estoquia-kdt@global.kawasaki.com', 20, 8, '2000-10-04', 1, 0, '2025-07-01', 1, NULL),
(569, 'Viernes', 'Lucky Aizon', 'Aizon', 'viernes-kdt', 'viernes-kdt@global.kawasaki.com', 8, 8, '2001-09-14', 0, 0, '2025-07-01', 1, NULL),
(570, 'Naito', 'Yudai', 'naito_yudai', 'naito_yudai', 'naito_yudai@global.kawasaki.com', 12, 62, '1992-12-18', 0, 1, '2025-09-01', 1, NULL),
(571, 'De Sotto', 'Francis  John', 'Kiko', 'desotto_f-kdt', 'desotto_f-kdt@global.kawasaki.com', 12, 3, '1990-11-05', 0, 0, '2026-01-02', 1, NULL),
(572, 'Banaag', 'Samantha Nicole', 'Sam', 'banaag-kdt', 'banaag-kdt@global.kawasaki.com', 12, 8, '2001-12-13', 1, 0, '2026-01-02', 1, NULL),
(573, 'Tapat', 'Sean Airon', 'Sean', 'tapat-kdt', 'tapat-kdt@global.kawasaki.com', 12, 8, '2002-08-26', 0, 0, '2026-01-02', 1, NULL),
(574, 'Samortin', 'Raymond Kent', 'Mon', 'samortin-kdt', 'samortin-kdt@global.kawasaki.com', 12, 8, '1998-07-23', 0, 0, '2026-01-02', 1, NULL),
(575, 'Concepcion', 'Aldous Henry', 'Aldous', 'Kuya  Aldous', 'Concepcion@global.kawasaki.com', 2, 44, '1971-09-04', 0, 1, '2026-01-02', 1, NULL),
(576, 'Villarin', 'Heinrich Allan', 'Hein', 'villarin-kdt', 'villarin-kdt@global.kawasaki.com', 23, 8, '2001-07-04', 0, 0, '2026-01-02', 1, NULL),
(577, 'Gamalo', 'Princess Camelle', 'Princess', 'gamalo-kdt', 'gamalo-kdt@global.kawasaki.com', 23, 8, '2001-10-09', 1, 0, '2026-01-02', 1, NULL),
(578, 'Ramirez', ' Kier Francklin', 'Kiko', 'ramirez_k-kdt', 'ramirez_k-kdt@global.kawasaki.com', 23, 8, '1999-09-06', 0, 0, '2026-01-02', 1, NULL),
(579, 'Calibuso', 'Renz Mar', 'Renz', 'calibuso-kdt', 'calibuso-kdt@global.kawasaki.com', 23, 8, '2001-08-31', 0, 0, '2026-01-02', 1, NULL),
(580, 'Fujita', 'Shizuo', 'Shizuo', 'fujita_s', 'fujita_s@global.kawasaki.com', 2, 63, '1967-04-30', 0, 1, '2026-01-05', 1, NULL),
(10008, 'Chiba', 'Tatsurou', '', 'chiba_ta', '', 0, 7, '0000-00-00', 0, 0, '0000-00-00', 1, '0000-00-00'),
(10018, 'Ueno', 'Ryosuke', '', 'ueno_r', '', 0, 18, '0000-00-00', 0, 0, '0000-00-00', 1, '0000-00-00'),
(10035, 'Iwamura', 'Munechiyo', '', 'iwamura_m', '', 0, 2, '0000-00-00', 0, 0, '0000-00-00', 1, '0000-00-00'),
(20001, 'Laureano', 'Antonio', 'Toni', 'toni', 'laureano-kdt@global.kawasaki.com', 2, 49, '1956-07-05', 0, 1, '2021-07-01', 1, '2025-07-30'),
(20002, 'Ampig', 'Rommel', 'Rommel', 'ampig-kdt', 'ampig-kdt@global.kawasaki.com', 13, 51, '1972-01-15', 0, 1, '2022-04-01', 1, '0000-00-00'),
(20003, 'Caveiro', 'Vincent', 'Vince', 'vince', 'caveiro-kdt@global.kawasaki.com', 15, 53, '1986-01-02', 0, 1, '2023-04-01', 1, '2025-04-02'),
(30001, 'Yonezawa', '', '', 'yonezawa-bnc', '', 0, 0, '0000-00-00', 0, 0, '0000-00-00', 1, '0000-00-00'),
(30040, 'Claudio', 'Agusto', 'Kuya August', 'no pc', 'NA@global.kawasaki.com', 2, 40, '1985-08-21', 0, 1, '2023-08-18', 1, '2025-01-10'),
(30041, 'Mizuno', 'Keisuke', 'Mizuno-san', 'mizuno_ke', 'mizuno_ke@global.kawasaki.com', 20, 5, '1990-01-10', 0, 0, '2025-01-07', 1, '2025-05-15'),
(30042, 'Ashida', 'Naoki', 'Ashida-san', 'ashida_n', 'ashida_n@global.kawasaki.com', 20, 5, '1990-01-01', 0, 0, '2025-01-07', 1, '2025-05-15'),
(30043, 'Kawabata', 'Taku', 'Kawabata-san', 'kawabata_taku', 'kawabata_taku@global.kawasaki.com', 20, 5, '1990-01-01', 0, 0, '2025-01-07', 1, '2025-05-15'),
(30044, 'Takata', 'Masayuki', 'Takata-san', 'takata_masa', 'takata_masa@global.kawasaki.com', 12, 5, '1990-01-01', 0, 0, '2025-01-07', 1, '2025-05-23'),
(30045, 'Sakuno', 'Ryoya', 'Sakuno-san', 'sakuno_ryoya', 'sakuno_ryoya@global.kawasaki.com', 12, 5, '1990-01-01', 0, 0, '2025-01-07', 1, '2025-05-23'),
(30046, 'Koizumi', 'Yuki', 'koizumi_yu', 'koizumi_yu', 'koizumi_yu@global.kawasaki.com', 8, 4, '1998-06-11', 0, 0, '2025-01-07', 1, '2025-05-16'),
(30063, 'Concepcion', 'Aldous', 'Aldous', 'eg. juan', 'eg. juan@global.kawasaki.com', 2, 44, '1971-09-04', 0, 1, '2025-09-15', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `group_list`
--

CREATE TABLE `group_list` (
  `id` int(11) NOT NULL,
  `abbreviation` varchar(5) NOT NULL,
  `name` text NOT NULL,
  `dept_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `group_list`
--

INSERT INTO `group_list` (`id`, `abbreviation`, `name`, `dept_id`) VALUES
(1, 'ACT', 'Accounting Group', 1),
(2, 'ADM', 'Admin', 1),
(3, 'ANA', 'Analysis Group', 2),
(4, 'CHE', 'Chemical Group', 9),
(5, 'CIV', 'Civil Group', 4),
(6, 'CEM', 'Cement Group', 3),
(7, 'CRY', 'Cryogenic Group', 9),
(8, 'ENV', 'Environmental Group', 5),
(9, 'ETCL', 'EarthTechnica Co., Ltd. Group', 3),
(10, 'IT', 'IT Group', 2),
(11, 'MPM', 'Machine Propulsion Group', 3),
(12, 'MH', 'Materials Handling Group', 6),
(13, 'MIL', 'Mill Group', 3),
(14, 'MNG', 'Managerial Group', 1),
(15, 'PIP', 'Piping Group', 4),
(16, 'SYS', 'Systems Group', 2),
(18, 'BOI', 'Boiler Group', 8),
(19, 'DXT', 'DX Team', 0),
(20, 'EE', 'Electrical Engineering Group', 7),
(22, 'SHI', 'Ship Group', 2),
(23, 'KRM', 'Kawasaki Railcar Manufacturing', 10),
(24, 'TEST', 'TEST GROUP', 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `department_list`
--
ALTER TABLE `department_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `designation_list`
--
ALTER TABLE `designation_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispatch_list`
--
ALTER TABLE `dispatch_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispatch_list_history`
--
ALTER TABLE `dispatch_list_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `dispatch_list_logs`
--
ALTER TABLE `dispatch_list_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_group`
--
ALTER TABLE `employee_group`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `employee_list`
--
ALTER TABLE `employee_list`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `group_list`
--
ALTER TABLE `group_list`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `department_list`
--
ALTER TABLE `department_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `designation_list`
--
ALTER TABLE `designation_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `dispatch_list`
--
ALTER TABLE `dispatch_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `dispatch_list_history`
--
ALTER TABLE `dispatch_list_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_list_logs`
--
ALTER TABLE `dispatch_list_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employee_group`
--
ALTER TABLE `employee_group`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=414;

--
-- AUTO_INCREMENT for table `employee_list`
--
ALTER TABLE `employee_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30064;

--
-- AUTO_INCREMENT for table `group_list`
--
ALTER TABLE `group_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
