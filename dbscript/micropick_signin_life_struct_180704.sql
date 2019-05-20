/*
Date: 2018-07-04 10:15:18
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for sl_ad
-- ----------------------------
DROP TABLE IF EXISTS `sl_ad`;
CREATE TABLE `sl_ad` (
  `ad_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sponsor_name` varchar(50) DEFAULT NULL,
  `sponsor_head` varchar(100) DEFAULT NULL,
  `title` varchar(20) DEFAULT NULL,
  `pic` varchar(100) DEFAULT NULL,
  `link` varchar(100) DEFAULT NULL,
  `pv_max` int(10) unsigned DEFAULT NULL,
  `position` varchar(30) DEFAULT NULL,
  `start_time` int(10) unsigned DEFAULT '0',
  `end_time` int(10) unsigned DEFAULT '0',
  `status` char(1) DEFAULT '1',
  `add_time` int(10) unsigned DEFAULT NULL,
  `pv` int(11) unsigned DEFAULT '0',
  `uv` int(10) unsigned DEFAULT '0',
  `income` decimal(10,2) unsigned DEFAULT '0.00',
  `forward` tinyint(3) unsigned DEFAULT '1',
  PRIMARY KEY (`ad_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_app_active
-- ----------------------------
DROP TABLE IF EXISTS `sl_app_active`;
CREATE TABLE `sl_app_active` (
  `app_version` varchar(10) DEFAULT '' COMMENT 'app显示版本',
  `core_version` int(32) DEFAULT '0' COMMENT 'app核心版本',
  `client_code` varchar(50) DEFAULT '' COMMENT '机器码',
  `client_system` varchar(10) DEFAULT '' COMMENT '系统',
  `client_version` varchar(10) DEFAULT '' COMMENT '版本',
  `client_brand` varchar(10) DEFAULT '' COMMENT '品牌',
  `client_model` varchar(20) DEFAULT '' COMMENT '型号',
  `client_width` int(10) unsigned DEFAULT '0' COMMENT '屏宽',
  `client_height` int(10) unsigned DEFAULT '0' COMMENT '屏高',
  `client_ip` varchar(15) DEFAULT '',
  `add_time` int(10) unsigned DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_app_token
-- ----------------------------
DROP TABLE IF EXISTS `sl_app_token`;
CREATE TABLE `sl_app_token` (
  `user_id` int(10) unsigned DEFAULT '0',
  `openid` varchar(50) DEFAULT '',
  `app_version` varchar(10) DEFAULT '' COMMENT 'app显示版本',
  `core_version` int(32) DEFAULT '0' COMMENT 'app核心版本',
  `access_token` varchar(50) NOT NULL DEFAULT '' COMMENT '授权令牌',
  `refresh_token` varchar(50) DEFAULT '' COMMENT '刷新令牌',
  `access_time` int(10) unsigned DEFAULT '0' COMMENT '过期时间',
  `client_code` varchar(50) DEFAULT '' COMMENT '机器码',
  `client_system` varchar(10) DEFAULT '' COMMENT '系统',
  `client_version` varchar(10) DEFAULT '' COMMENT '版本',
  `client_brand` varchar(10) DEFAULT '' COMMENT '品牌',
  `client_model` varchar(20) DEFAULT '' COMMENT '型号',
  `client_width` int(10) unsigned DEFAULT '0' COMMENT '屏宽',
  `client_height` int(10) unsigned DEFAULT '0' COMMENT '屏高',
  `client_ip` varchar(15) DEFAULT '',
  `city` varchar(10) DEFAULT '',
  `add_time` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`access_token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_app_version
-- ----------------------------
DROP TABLE IF EXISTS `sl_app_version`;
CREATE TABLE `sl_app_version` (
  `version_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_system` varchar(10) NOT NULL DEFAULT '',
  `app_version` varchar(10) NOT NULL DEFAULT '',
  `core_version` int(10) unsigned NOT NULL DEFAULT '0',
  `status` char(1) NOT NULL DEFAULT '1',
  `down_url` varchar(100) NOT NULL DEFAULT '',
  `update_content` text NOT NULL,
  PRIMARY KEY (`version_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_article
-- ----------------------------
DROP TABLE IF EXISTS `sl_article`;
CREATE TABLE `sl_article` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `author` varchar(10) NOT NULL DEFAULT '' COMMENT '作者',
  `content` text NOT NULL COMMENT '内容',
  `describe` varchar(255) NOT NULL DEFAULT '' COMMENT '文章摘要',
  `cate_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '分类id',
  `is_recommend` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否推荐 1 推荐 0 不推荐',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '文章类型 1 文章 2 视频',
  `read_num` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '默认阅读量',
  `link` varchar(100) NOT NULL DEFAULT '' COMMENT '外链跳转URL',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 未发布 1 已发布',
  `info` text NOT NULL COMMENT '图片列表',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_article_cate
-- ----------------------------
DROP TABLE IF EXISTS `sl_article_cate`;
CREATE TABLE `sl_article_cate` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(36) NOT NULL COMMENT '标题',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '父级id',
  `sort` int(4) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_article_user_relation
-- ----------------------------
DROP TABLE IF EXISTS `sl_article_user_relation`;
CREATE TABLE `sl_article_user_relation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户id',
  `article_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '文章id',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 站内阅读 1 收藏 2 分享阅读',
  `add_time` int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `status` int(11) NOT NULL DEFAULT '0' COMMENT 'type = 0 (0 正常 1 已给积分) type = 1 (分享者id)',
  PRIMARY KEY (`id`),
  KEY `select` (`user_id`,`type`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_challenge
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge`;
CREATE TABLE `sl_challenge` (
  `challenge_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  `price` int(10) unsigned NOT NULL,
  `day` smallint(5) unsigned NOT NULL,
  `btime` smallint(5) unsigned NOT NULL DEFAULT '500',
  `etime` smallint(5) unsigned NOT NULL DEFAULT '800',
  `join_date` int(10) unsigned NOT NULL,
  `expire_date` int(10) unsigned NOT NULL,
  `event` varchar(10) NOT NULL,
  `status` int(8) unsigned NOT NULL DEFAULT '0',
  `auto` char(1) NOT NULL DEFAULT '1',
  `add_time` int(10) unsigned NOT NULL,
  `edit_time` int(10) unsigned NOT NULL,
  `stat_time` int(10) unsigned NOT NULL,
  `income` int(10) unsigned NOT NULL,
  PRIMARY KEY (`challenge_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_challenge_best
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_best`;
CREATE TABLE `sl_challenge_best` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(11) unsigned NOT NULL,
  `earlier_uid` int(11) unsigned NOT NULL,
  `earlier_time` int(11) unsigned NOT NULL,
  `earlier_nickname` varchar(10) NOT NULL,
  `earlier_avator` varchar(150) NOT NULL,
  `insist_uid` int(11) unsigned NOT NULL,
  `insist_day` int(11) unsigned NOT NULL,
  `insist_nickname` varchar(10) NOT NULL,
  `insist_avator` varchar(150) NOT NULL,
  `win_num` int(11) unsigned NOT NULL,
  `lose_num` int(11) unsigned NOT NULL,
  `edit_time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_challenge_both
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_both`;
CREATE TABLE `sl_challenge_both` (
  `both_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `price` int(10) unsigned NOT NULL,
  `day` smallint(5) unsigned NOT NULL,
  `join_date` int(10) unsigned NOT NULL,
  `expire_date` int(10) unsigned NOT NULL,
  `launch_uid` int(10) unsigned NOT NULL DEFAULT '0',
  `launch_time` int(10) unsigned NOT NULL DEFAULT '0',
  `launch_cid` int(10) unsigned NOT NULL DEFAULT '0',
  `launch_status` char(1) NOT NULL DEFAULT '0',
  `accept_uid` int(10) unsigned NOT NULL DEFAULT '0',
  `accept_time` int(10) unsigned NOT NULL DEFAULT '0',
  `accept_cid` int(10) unsigned NOT NULL DEFAULT '0',
  `accept_status` char(1) NOT NULL DEFAULT '0',
  `recommend` char(1) NOT NULL DEFAULT '0',
  `status` char(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`both_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_challenge_dynamic
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_dynamic`;
CREATE TABLE `sl_challenge_dynamic` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nickname` varchar(20) NOT NULL DEFAULT '',
  `avator` varchar(150) NOT NULL DEFAULT '',
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  `event` varchar(20) NOT NULL DEFAULT '',
  `event_id` int(10) unsigned NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1',
  `add_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_challenge_dynamic_read
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_dynamic_read`;
CREATE TABLE `sl_challenge_dynamic_read` (
  `read_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `dynamic_id` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  `status` char(1) NOT NULL DEFAULT '1',
  `add_time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`read_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_challenge_income
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_income`;
CREATE TABLE `sl_challenge_income` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` int(11) unsigned NOT NULL,
  `user_id` int(11) unsigned NOT NULL,
  `nickname` varchar(10) NOT NULL,
  `avator` varchar(150) NOT NULL,
  `income` decimal(10,2) unsigned NOT NULL,
  `day` int(11) unsigned NOT NULL,
  `add_time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_challenge_record
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_record`;
CREATE TABLE `sl_challenge_record` (
  `record_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `challenge_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `date` int(10) unsigned NOT NULL,
  `btime` int(10) unsigned NOT NULL,
  `etime` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  `stime` int(10) unsigned NOT NULL DEFAULT '0',
  `status` char(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`record_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_challenge_room
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_room`;
CREATE TABLE `sl_challenge_room` (
  `room_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `day` smallint(5) unsigned NOT NULL DEFAULT '0',
  `nickname` varchar(10) NOT NULL,
  `city` varchar(10) NOT NULL,
  `title` varchar(20) NOT NULL,
  `btime` smallint(5) unsigned NOT NULL DEFAULT '0',
  `etime` smallint(5) unsigned NOT NULL DEFAULT '0',
  `leader_rate` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `leader_income` int(10) unsigned NOT NULL DEFAULT '0',
  `income_rate` decimal(10,6) unsigned NOT NULL DEFAULT '0.000000',
  `expire_time` int(10) unsigned NOT NULL DEFAULT '0',
  `add_time` int(10) unsigned NOT NULL DEFAULT '0',
  `join_count` int(10) unsigned NOT NULL,
  `join_time` int(10) unsigned NOT NULL DEFAULT '0',
  `price_lose` int(10) unsigned NOT NULL DEFAULT '0',
  `price_all` int(10) unsigned NOT NULL,
  `price_ing` int(10) unsigned NOT NULL,
  `price_grant` int(10) unsigned NOT NULL,
  `price_subsidy` int(10) unsigned NOT NULL,
  `price_tax` int(10) unsigned NOT NULL,
  `subsidy` int(10) unsigned NOT NULL,
  `max_rate` decimal(10,6) unsigned NOT NULL,
  `avator` varchar(150) NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1',
  `recommend_time` int(10) unsigned NOT NULL,
  `pic` varchar(100) NOT NULL,
  `mode` char(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`room_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_challenge_subsidy
-- ----------------------------
DROP TABLE IF EXISTS `sl_challenge_subsidy`;
CREATE TABLE `sl_challenge_subsidy` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `day` smallint(6) unsigned NOT NULL,
  `price_min1` int(11) unsigned NOT NULL,
  `price_max1` int(11) unsigned NOT NULL,
  `subsidy11` decimal(10,2) unsigned NOT NULL,
  `subsidy12` decimal(10,2) unsigned NOT NULL,
  `price_min2` int(11) unsigned NOT NULL,
  `price_max2` int(11) unsigned NOT NULL,
  `subsidy21` decimal(10,2) unsigned NOT NULL,
  `subsidy22` decimal(10,2) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_goods
-- ----------------------------
DROP TABLE IF EXISTS `sl_goods`;
CREATE TABLE `sl_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` bigint(20) unsigned NOT NULL COMMENT '淘宝商品id',
  `name` varchar(255) NOT NULL COMMENT '商品名',
  `d_name` varchar(50) NOT NULL COMMENT '商品短名',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '主图',
  `cut_img` varchar(255) NOT NULL DEFAULT '' COMMENT '缩略图',
  `cid` int(11) unsigned NOT NULL COMMENT '分类id 美食:1 、母婴:4 、水果:13、服饰:14、百货:15、美妆:16、电器:18、男装:743、 家纺:818、鞋包:1281、运动:1451、手机:1543',
  `org_price` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '正常售价',
  `price` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '券后价',
  `sales_num` int(10) unsigned NOT NULL COMMENT '商品销量',
  `dsr` decimal(4,2) NOT NULL COMMENT '商品描述分',
  `commission` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '佣金比例',
  `jihua_link` varchar(255) NOT NULL DEFAULT '' COMMENT '计划链接',
  `coupon_price` smallint(11) unsigned NOT NULL DEFAULT '0' COMMENT '优惠券金额',
  `coupon_time` int(11) NOT NULL DEFAULT '0' COMMENT '优惠券结束时间*',
  `coupon_surplus_quantity` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '优惠券剩余数量',
  `coupon_total_quantity` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '优惠券总数量',
  `coupon_min_order_amount` int(11) NOT NULL DEFAULT '0' COMMENT '优惠券使用条件',
  `good_quan_link` varchar(255) NOT NULL DEFAULT '' COMMENT '券 商 二合一链接  默认app',
  `good_quan_link_h5` varchar(255) NOT NULL DEFAULT '' COMMENT 'h5 券商链接',
  `tao_pass` char(15) NOT NULL DEFAULT '' COMMENT '淘口令',
  `status` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '状态 1 不显示',
  `recommend` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '0 未推荐  1 已推荐',
  `type` tinyint(1) unsigned NOT NULL DEFAULT '1' COMMENT '商品来源 1 拼多多 2 淘宝',
  PRIMARY KEY (`id`),
  KEY `name code` (`status`,`name`) USING BTREE,
  KEY `recommend code` (`status`,`recommend`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for sl_goods_cate_custom
-- ----------------------------
DROP TABLE IF EXISTS `sl_goods_cate_custom`;
CREATE TABLE `sl_goods_cate_custom` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(5) NOT NULL COMMENT '分类名',
  `keywords` varchar(255) NOT NULL COMMENT '关键字',
  `sort` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '排序',
  `sub_cate` varchar(255) NOT NULL DEFAULT '' COMMENT '子分类',
  `seo_title` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_goods_promotion_day_stat
-- ----------------------------
DROP TABLE IF EXISTS `sl_goods_promotion_day_stat`;
CREATE TABLE `sl_goods_promotion_day_stat` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `buy_count` int(11) unsigned NOT NULL COMMENT '购买人数',
  `order_count` int(11) NOT NULL COMMENT '订单数',
  `super_count` int(11) NOT NULL COMMENT '新增超级会员',
  `predict_commission_settlement` int(11) NOT NULL,
  `predict_commission_issued` int(11) NOT NULL,
  `stat_month` int(11) NOT NULL COMMENT '结算月',
  `add_time` int(11) NOT NULL COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_goods_promotion_stat
-- ----------------------------
DROP TABLE IF EXISTS `sl_goods_promotion_stat`;
CREATE TABLE `sl_goods_promotion_stat` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_commission` int(11) NOT NULL DEFAULT '0' COMMENT '平台获得佣金',
  `grant_commission` int(11) NOT NULL DEFAULT '0' COMMENT '平台发放佣金',
  `add_time` int(11) NOT NULL COMMENT '添加时间',
  `stat_date` int(11) NOT NULL,
  `grant_rate` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '发放比率',
  `commission_profit` int(11) NOT NULL DEFAULT '0' COMMENT '佣金利润',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 待发放 1 已发放',
  `stat_grant_commission` int(11) NOT NULL DEFAULT '0' COMMENT '结算后发放的佣金',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_order
-- ----------------------------
DROP TABLE IF EXISTS `sl_order`;
CREATE TABLE `sl_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(36) NOT NULL DEFAULT '0' COMMENT '订单号',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '下单时间',
  `modify_at_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '最后更新时间',
  `order_amount` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '订单价格',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '订单状态 1 已付款 2 已收货 3 已失效 4 拼多多平台已结算 5 本平台已结算 6 本平台结算审核',
  `platform_rebeat` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT '平台获得返点(千分比)',
  `platform_commission` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '平台获得佣金',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '获得佣金用户id',
  `directly_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '直属用户id',
  `directly_supervisor_user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '直属上级用户id',
  `user_commission` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '用户佣金',
  `directly_user_commission` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '上级用户佣金',
  `directly_supervisor_user_commission` int(11) unsigned NOT NULL COMMENT '上上级用户佣金',
  `p_id` varchar(30) NOT NULL DEFAULT '',
  `team_level` varchar(150) NOT NULL DEFAULT '' COMMENT '团队会员等级',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_order_goods
-- ----------------------------
DROP TABLE IF EXISTS `sl_order_goods`;
CREATE TABLE `sl_order_goods` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `good_id` int(11) NOT NULL,
  `good_name` varchar(255) NOT NULL,
  `img` varchar(255) NOT NULL,
  `good_price` int(11) NOT NULL DEFAULT '0' COMMENT '市场价',
  `good_num` smallint(6) NOT NULL DEFAULT '1' COMMENT '商品数量',
  `tao_pass` varchar(20) NOT NULL DEFAULT '',
  `quan_price` smallint(6) NOT NULL DEFAULT '0',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 白拿订单 2 普通订单',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for sl_order_take
-- ----------------------------
DROP TABLE IF EXISTS `sl_order_take`;
CREATE TABLE `sl_order_take` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `gid` int(11) unsigned NOT NULL COMMENT '商品id',
  `residue_day` tinyint(1) unsigned NOT NULL COMMENT '剩余天数',
  `day` tinyint(1) unsigned NOT NULL,
  `appoint_price` int(11) NOT NULL DEFAULT '0' COMMENT '押金',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 正在挑战 1 挑战成功 2 挑战失败',
  `add_time` int(11) NOT NULL DEFAULT '0' COMMENT '添加时间',
  `stat_date` int(11) NOT NULL DEFAULT '0' COMMENT '结算时间',
  `min_minute` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '打卡最少分钟数 (距离当天5点)',
  `max_minute` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '打卡最大分钟数 (距离当天5点)',
  `address_id` int(11) NOT NULL DEFAULT '0' COMMENT '地址id',
  `accept_name` varchar(20) NOT NULL DEFAULT '' COMMENT '收货人',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `address` varchar(50) NOT NULL DEFAULT '' COMMENT '地址',
  `express_name` varchar(10) NOT NULL DEFAULT '' COMMENT '快递名',
  `express_sn` varchar(50) NOT NULL DEFAULT '' COMMENT '快递单号',
  `good_type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 拼多多   2 淘客',
  `order_status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0 确认订单 1 已发货 2 已收货 3 完成订单',
  `pay_type` char(5) NOT NULL DEFAULT '' COMMENT '支付方式  1 余额支付  2 微信支付',
  `pay_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '第三方支付id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_order_take_signin
-- ----------------------------
DROP TABLE IF EXISTS `sl_order_take_signin`;
CREATE TABLE `sl_order_take_signin` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `take_id` int(11) unsigned NOT NULL COMMENT '白拿id',
  `signin_time` int(11) unsigned NOT NULL COMMENT '打卡时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_pay
-- ----------------------------
DROP TABLE IF EXISTS `sl_pay`;
CREATE TABLE `sl_pay` (
  `pay_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appid` varchar(30) DEFAULT NULL,
  `openid` varchar(50) DEFAULT NULL,
  `out_trade_no` varchar(50) DEFAULT NULL,
  `result_code` varchar(10) DEFAULT NULL,
  `total_fee` int(10) unsigned DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `time_end` varchar(14) DEFAULT NULL,
  `trade_type` varchar(10) DEFAULT NULL,
  `data` text,
  `add_time` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`pay_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_problem_cate
-- ----------------------------
DROP TABLE IF EXISTS `sl_problem_cate`;
CREATE TABLE `sl_problem_cate` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(10) NOT NULL COMMENT '分类名',
  `sort` tinyint(1) NOT NULL COMMENT '排序',
  `img` varchar(150) NOT NULL COMMENT '分类图',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_problem_list
-- ----------------------------
DROP TABLE IF EXISTS `sl_problem_list`;
CREATE TABLE `sl_problem_list` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `cate_id` int(11) NOT NULL COMMENT '分类id',
  `question` varchar(255) NOT NULL COMMENT '问题',
  `answer` text NOT NULL COMMENT '答案',
  `sort` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_quote
-- ----------------------------
DROP TABLE IF EXISTS `sl_quote`;
CREATE TABLE `sl_quote` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL,
  `source` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_redpack_assign
-- ----------------------------
DROP TABLE IF EXISTS `sl_redpack_assign`;
CREATE TABLE `sl_redpack_assign` (
  `assign_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `redpack_id` int(10) unsigned NOT NULL DEFAULT '0',
  `price` int(10) unsigned NOT NULL DEFAULT '0',
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `draw_time` int(10) unsigned NOT NULL DEFAULT '0',
  `last_draw_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`assign_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_redpack_send
-- ----------------------------
DROP TABLE IF EXISTS `sl_redpack_send`;
CREATE TABLE `sl_redpack_send` (
  `redpack_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `room_id` int(10) unsigned DEFAULT NULL,
  `ad_id` int(10) unsigned DEFAULT '0',
  `event` varchar(10) DEFAULT NULL,
  `price` int(10) unsigned DEFAULT '0',
  `num` smallint(5) unsigned DEFAULT '0',
  `surplus` smallint(5) unsigned DEFAULT '0',
  `msg` varchar(100) DEFAULT NULL,
  `status` tinyint(3) unsigned DEFAULT NULL,
  `add_time` int(10) unsigned DEFAULT NULL,
  `draw_time` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`redpack_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_siteinfo
-- ----------------------------
DROP TABLE IF EXISTS `sl_siteinfo`;
CREATE TABLE `sl_siteinfo` (
  `key` varchar(20) NOT NULL COMMENT '键',
  `value` mediumtext NOT NULL COMMENT '值(序列化后)',
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC COMMENT='站点数据表';

-- ----------------------------
-- Table structure for sl_stat
-- ----------------------------
DROP TABLE IF EXISTS `sl_stat`;
CREATE TABLE `sl_stat` (
  `date` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `active_count` int(10) unsigned NOT NULL COMMENT '激活数',
  `regist_count` int(10) unsigned NOT NULL COMMENT '注册数',
  `pay_user` int(10) unsigned NOT NULL COMMENT '支付用户数',
  `payment` decimal(10,2) unsigned NOT NULL COMMENT '支付金额',
  `deposit` decimal(10,2) unsigned NOT NULL COMMENT '沉淀资金',
  `room_fee` decimal(10,2) unsigned NOT NULL COMMENT '族群费用',
  `mb_change` decimal(10,2) unsigned NOT NULL COMMENT 'M币兑换',
  `tax` decimal(10,2) unsigned NOT NULL COMMENT '上缴',
  `grant` decimal(10,2) unsigned NOT NULL COMMENT '挑战发放奖励',
  `subsidy` decimal(10,2) unsigned NOT NULL COMMENT '挑战补贴',
  `platform_commission` decimal(10,2) unsigned NOT NULL COMMENT '电商结算',
  `withdraw` decimal(10,2) unsigned NOT NULL COMMENT '提现金额',
  `channel` decimal(10,2) unsigned NOT NULL COMMENT '通道费用',
  `grant_commission` decimal(10,2) unsigned NOT NULL COMMENT '发放佣金',
  PRIMARY KEY (`date`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_stat_challenge_both
-- ----------------------------
DROP TABLE IF EXISTS `sl_stat_challenge_both`;
CREATE TABLE `sl_stat_challenge_both` (
  `date` int(10) unsigned NOT NULL,
  `day` tinyint(10) unsigned NOT NULL,
  `tax` decimal(10,2) unsigned NOT NULL,
  `grant` decimal(10,2) unsigned NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `all_price` decimal(10,2) unsigned NOT NULL,
  `all_count_user` int(10) unsigned NOT NULL,
  `all_count_record` int(10) unsigned NOT NULL,
  `lose_price` decimal(10,2) unsigned NOT NULL,
  `lose_count_user` int(10) unsigned NOT NULL,
  `lose_count_record` int(10) unsigned NOT NULL,
  `win_price` decimal(10,2) unsigned NOT NULL,
  `win_count_user` int(10) unsigned NOT NULL,
  `win_count_record` int(10) unsigned NOT NULL,
  `ing_price` decimal(10,2) unsigned NOT NULL,
  `ing_count_user` int(10) unsigned NOT NULL,
  `ing_count_record` int(10) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_stat_challenge_room
-- ----------------------------
DROP TABLE IF EXISTS `sl_stat_challenge_room`;
CREATE TABLE `sl_stat_challenge_room` (
  `date` int(10) unsigned NOT NULL,
  `room_id` int(10) unsigned NOT NULL,
  `day` int(10) unsigned NOT NULL,
  `nickname` varchar(20) NOT NULL,
  `tax` decimal(10,2) unsigned NOT NULL,
  `grant` decimal(10,2) unsigned NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `subsidy` decimal(10,2) unsigned NOT NULL,
  `leader_income` decimal(10,2) unsigned NOT NULL,
  `all_price` decimal(10,2) unsigned NOT NULL,
  `all_count_user` int(10) unsigned NOT NULL,
  `all_count_record` int(10) unsigned NOT NULL,
  `lose_price` decimal(10,2) unsigned NOT NULL,
  `lose_count_user` int(10) unsigned NOT NULL,
  `lose_count_record` int(10) unsigned NOT NULL,
  `win_price` decimal(10,2) unsigned NOT NULL,
  `win_count_user` int(10) unsigned NOT NULL,
  `win_count_record` int(10) unsigned NOT NULL,
  `ing_price` decimal(10,2) unsigned NOT NULL,
  `ing_count_user` int(10) unsigned NOT NULL,
  `ing_count_record` int(10) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_stat_mb_change
-- ----------------------------
DROP TABLE IF EXISTS `sl_stat_mb_change`;
CREATE TABLE `sl_stat_mb_change` (
  `date` int(10) unsigned NOT NULL,
  `mb` int(10) unsigned NOT NULL,
  `tax` decimal(10,2) unsigned DEFAULT NULL,
  `grant_mb` int(10) unsigned NOT NULL,
  `grant_money` decimal(10,2) NOT NULL,
  `grant_count` int(10) NOT NULL,
  `surplus` int(10) NOT NULL,
  `profit` decimal(10,2) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_user
-- ----------------------------
DROP TABLE IF EXISTS `sl_user`;
CREATE TABLE `sl_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `openid` varchar(32) NOT NULL DEFAULT '',
  `openid_android` varchar(32) NOT NULL DEFAULT '',
  `openid_app` varchar(32) NOT NULL DEFAULT '',
  `unionid` varchar(32) NOT NULL DEFAULT '',
  `nickname` varchar(20) NOT NULL DEFAULT '',
  `avator` varchar(150) NOT NULL DEFAULT '' COMMENT '头像',
  `balance` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '余额',
  `integral` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '积分',
  `mb` int(11) unsigned NOT NULL COMMENT 'M币',
  `status` char(1) NOT NULL DEFAULT '1' COMMENT '状态',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  `sex` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 男 2 女',
  `subscribe_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关注时间',
  `mobile` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT '手机号',
  `password` char(32) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_user_address
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_address`;
CREATE TABLE `sl_user_address` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(8) unsigned NOT NULL DEFAULT '0',
  `consignee` varchar(60) NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号',
  `province_code` mediumint(5) unsigned NOT NULL DEFAULT '0',
  `province` varchar(20) NOT NULL DEFAULT '' COMMENT '省',
  `city_code` mediumint(5) unsigned NOT NULL DEFAULT '0',
  `city` varchar(20) NOT NULL DEFAULT '' COMMENT '市',
  `district_code` mediumint(5) unsigned NOT NULL DEFAULT '0',
  `district` varchar(20) NOT NULL DEFAULT '' COMMENT '区',
  `address` varchar(80) NOT NULL DEFAULT '' COMMENT '详细地址',
  `zipcode` varchar(10) NOT NULL DEFAULT '',
  `default` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '默认地址',
  `add_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='用户收货表';

-- ----------------------------
-- Table structure for sl_user_dredge_eduities
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_dredge_eduities`;
CREATE TABLE `sl_user_dredge_eduities` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `type` tinyint(1) NOT NULL COMMENT '开通方式 1 包月 2 包年',
  `order_no` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号',
  `price` int(11) unsigned NOT NULL COMMENT '支付价格',
  `add_time` int(11) unsigned NOT NULL COMMENT '添加时间',
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '上级用户id',
  `superior_award` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '晋升津贴',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_dynamic
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_dynamic`;
CREATE TABLE `sl_user_dynamic` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `nickname` varchar(20) NOT NULL DEFAULT '',
  `avator` varchar(150) NOT NULL DEFAULT '',
  `receive_uid` int(10) unsigned NOT NULL,
  `event` varchar(20) NOT NULL DEFAULT '',
  `event_id` int(10) unsigned NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1',
  `add_time` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_friend
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_friend`;
CREATE TABLE `sl_user_friend` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `friend_uid` int(10) unsigned NOT NULL,
  `referer` varchar(20) NOT NULL,
  `add_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_goods_footprint
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_goods_footprint`;
CREATE TABLE `sl_user_goods_footprint` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT '1 商品  2 商品分类',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `rid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '关联id',
  `name` varchar(50) NOT NULL DEFAULT '',
  `add_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '添加时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_identity
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_identity`;
CREATE TABLE `sl_user_identity` (
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `real_name` varchar(20) NOT NULL COMMENT '真实姓名',
  `front_id_card` varchar(100) NOT NULL COMMENT '身份证正面',
  `back_id_card` varchar(100) NOT NULL COMMENT '身份证反面',
  `add_time` int(11) NOT NULL COMMENT '添加时间',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '审核状态 0 待审核 1 通过 2 未通过',
  `fail_cause` varchar(80) NOT NULL DEFAULT '' COMMENT '失败原因',
  `ali_account` varchar(50) NOT NULL DEFAULT '' COMMENT '支付宝账号',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_income
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_income`;
CREATE TABLE `sl_user_income` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `nickname` varchar(10) NOT NULL,
  `avator` varchar(150) NOT NULL,
  `income` decimal(10,2) unsigned NOT NULL,
  `day` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_integral
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_integral`;
CREATE TABLE `sl_user_integral` (
  `flow_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `integral` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `event` varchar(20) NOT NULL DEFAULT '',
  `event_id` int(10) unsigned NOT NULL DEFAULT '0',
  `event_name` varchar(20) NOT NULL DEFAULT '',
  `source` char(1) NOT NULL DEFAULT '0' COMMENT '来源 1 h5 2 andriod 3 ios',
  `add_time` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`flow_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_invite_code
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_invite_code`;
CREATE TABLE `sl_user_invite_code` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户邀请码表',
  `code` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Table structure for sl_user_mb
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_mb`;
CREATE TABLE `sl_user_mb` (
  `flow_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `mb` int(10) NOT NULL DEFAULT '0',
  `balance` int(10) NOT NULL DEFAULT '0',
  `event` varchar(30) NOT NULL DEFAULT '',
  `event_id` int(10) unsigned NOT NULL DEFAULT '0',
  `event_name` varchar(20) NOT NULL DEFAULT '',
  `source` char(1) NOT NULL DEFAULT '0' COMMENT '来源 1 h5 2 andriod 3 ios',
  `add_time` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`flow_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_mobile_verify
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_mobile_verify`;
CREATE TABLE `sl_user_mobile_verify` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` char(15) NOT NULL DEFAULT '' COMMENT 'ip地址',
  `mobile` varchar(11) NOT NULL,
  `code` varchar(6) NOT NULL,
  `expire_time` int(10) unsigned NOT NULL,
  `verify` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `add_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='手机验证码表';

-- ----------------------------
-- Table structure for sl_user_money
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_money`;
CREATE TABLE `sl_user_money` (
  `flow_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL DEFAULT '0',
  `money` decimal(10,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `event` varchar(30) NOT NULL DEFAULT '',
  `event_id` int(10) unsigned NOT NULL DEFAULT '0',
  `event_name` varchar(20) NOT NULL DEFAULT '',
  `source` char(1) NOT NULL DEFAULT '0' COMMENT '来源 1 h5 2 andriod 3 ios',
  `add_time` int(10) unsigned DEFAULT '0',
  PRIMARY KEY (`flow_id`),
  KEY `user_id` (`user_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_promotion
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_promotion`;
CREATE TABLE `sl_user_promotion` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p_id` varchar(30) NOT NULL DEFAULT '' COMMENT '推广位id',
  `expire_time` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '到期时间',
  `invite_code` int(11) unsigned NOT NULL COMMENT '邀请码',
  `is_couple_weal` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否有新用户福利 1 是 0 否',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_user_tier
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_tier`;
CREATE TABLE `sl_user_tier` (
  `user_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '邀请人id',
  `add_time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_user_withdraw
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_withdraw`;
CREATE TABLE `sl_user_withdraw` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `real_name` varchar(20) NOT NULL COMMENT '真实姓名',
  `pay_type` varchar(15) NOT NULL COMMENT '支付方式',
  `price` int(11) unsigned NOT NULL COMMENT '提现金额',
  `order_no` varchar(50) NOT NULL COMMENT '订单号',
  `transfer_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '提现记录id',
  `status` tinyint(1) NOT NULL COMMENT '0 待审核 1 成功 2 失败',
  `add_time` int(11) NOT NULL COMMENT '添加时间',
  `user_proof` varchar(80) NOT NULL COMMENT '用户凭证 openid 或 支付宝账号',
  `result` text NOT NULL COMMENT '失败原因',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Table structure for sl_user_withdraw_tencent
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_withdraw_tencent`;
CREATE TABLE `sl_user_withdraw_tencent` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(30) NOT NULL COMMENT '公总号appid',
  `secret` varchar(36) NOT NULL COMMENT '公众号秘钥',
  `token` varchar(20) NOT NULL COMMENT 'token',
  `aes_key` varchar(50) NOT NULL COMMENT '加密串',
  `mch_id` varchar(20) NOT NULL COMMENT '商户号',
  `key` varchar(36) NOT NULL COMMENT '商户密钥',
  `qrcode` varchar(100) NOT NULL COMMENT '公众号二维码',
  `add_time` int(11) unsigned NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 启用 0 停用',
  `title` char(10) NOT NULL COMMENT '公众号标题',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for sl_user_withdraw_tencent_relation
-- ----------------------------
DROP TABLE IF EXISTS `sl_user_withdraw_tencent_relation`;
CREATE TABLE `sl_user_withdraw_tencent_relation` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL COMMENT '用户id',
  `withdraw_tencent_id` int(11) unsigned NOT NULL COMMENT '提现公众号id',
  `openid` varchar(32) NOT NULL COMMENT '对应openid',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for sl_warm_tips
-- ----------------------------
DROP TABLE IF EXISTS `sl_warm_tips`;
CREATE TABLE `sl_warm_tips` (
  `id` int(255) unsigned NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;


-- 2.0.1 update
CREATE TABLE `sl_ad_data` (
`id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
`idfa`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '广告标识' ,
`reg_date`  datetime NOT NULL COMMENT '时间' ,
`os`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '手机系统类型 1：ios  2:android' ,
`version`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '手机系统版本' ,
`appstore`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '1:appstore 2:huawe 3:应用宝 4.小米 5.vivo 6.oppo 7.魅族 8.百度 9.91助手 10.阿里' ,
`platform`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT '广告平台' ,
`ip`  varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT 'IP地址' ,
`mac`  varchar(48) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' COMMENT 'MAC地址' ,
`add_time`  int(11) NOT NULL COMMENT '添加时间' ,
`uid`  varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' ,
`appid`  varchar(24) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '' ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0;
ALTER TABLE `sl_app_active` ADD COLUMN `xg_token`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' AFTER `client_ip`;
ALTER TABLE `sl_app_token` ADD COLUMN `xg_token`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '' AFTER `city`;
CREATE TABLE `sl_goods_marketing` (
`id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
`name`  char(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '发布人' ,
`avatar`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`desc`  varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '分享详情' ,
`info`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT '分享商品信息' ,
`add_time`  int(11) UNSIGNED NOT NULL COMMENT '添加时间' ,
`type`  tinyint(1) NOT NULL COMMENT '1 爆款商品 2 营销素材' ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0;
CREATE TABLE `sl_xinge_task` (
`id`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
`event`  varchar(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`user_id`  int(10) UNSIGNED NOT NULL ,
`data`  text CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0;


-- 2.0.5 update
CREATE TABLE `sl_invite_sms_send` (
`id`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
`user_id`  int(10) UNSIGNED NOT NULL ,
`mobile`  varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL ,
`add_time`  int(10) UNSIGNED NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Fixed
DELAY_KEY_WRITE=0
;

CREATE TABLE `sl_invite_sms_set` (
`id`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
`user_id`  int(10) UNSIGNED NOT NULL ,
`uname`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`content`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`link`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`edit_time`  int(10) UNSIGNED NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0
;

ALTER TABLE `sl_user` ADD COLUMN `wechat`  varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `password`;
ALTER TABLE `sl_user` ADD COLUMN `wxname`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `wechat`;
UPDATE `sl_user` SET `wxname` = `nickname`;

CREATE TABLE `sl_user_contact` (
`id`  int(11) UNSIGNED NOT NULL AUTO_INCREMENT ,
`user_id`  int(10) UNSIGNED NOT NULL ,
`uname`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`mobile`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`add_time`  int(10) UNSIGNED NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0
;

ALTER TABLE `sl_stat` ADD COLUMN `less_1yuan_count`  int(10) UNSIGNED NOT NULL COMMENT '余额不足1元用户数' AFTER `grant_commission`;
ALTER TABLE `sl_stat` ADD COLUMN `less_1yuan_balance`  decimal(10,2) UNSIGNED NOT NULL COMMENT '余额不足1元总数' AFTER `less_1yuan_count`;

-- 2.0.5 perfect
ALTER TABLE `sl_goods` ADD COLUMN `edit_time`  int(11) UNSIGNED NOT NULL COMMENT '修改时间' AFTER `type`;
CREATE UNIQUE INDEX `unique_goods_id` ON `sl_goods`(`goods_id`) USING BTREE ;

-- 2.2.5
CREATE TABLE `sl_redpack_auto` (
`id`  int(10) UNSIGNED NOT NULL AUTO_INCREMENT ,
`user_id`  int(10) UNSIGNED NOT NULL ,
`room_id`  int(10) UNSIGNED NOT NULL ,
`auto`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '1' ,
`mb`  int(10) UNSIGNED NOT NULL ,
`num`  int(10) UNSIGNED NOT NULL ,
`receive`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`share`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' ,
`pic`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`msg`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`edit_time`  int(10) UNSIGNED NOT NULL ,
PRIMARY KEY (`id`)
)
ENGINE=MyISAM
DEFAULT CHARACTER SET=utf8 COLLATE=utf8_general_ci
CHECKSUM=0
ROW_FORMAT=Dynamic
DELAY_KEY_WRITE=0
;
ALTER TABLE `sl_redpack_send` ADD COLUMN `show`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '1' AFTER `draw_time`;
ALTER TABLE `sl_redpack_send` ADD COLUMN `receive`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'room' AFTER `show`;
ALTER TABLE `sl_redpack_send` ADD COLUMN `share`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '0' AFTER `receive`;
ALTER TABLE `sl_redpack_send` ADD COLUMN `transfer`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'account' AFTER `share`;
ALTER TABLE `sl_redpack_send` ADD COLUMN `pic`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `transfer`;
ALTER TABLE `sl_redpack_send` ADD COLUMN `short_url`  varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `pic`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `user_id`  int(10) UNSIGNED NOT NULL AFTER `redpack_id`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `room_id`  int(10) UNSIGNED NOT NULL AFTER `user_id`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `event`  varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `room_id`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `price`  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `event`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `num`  smallint(5) UNSIGNED NOT NULL DEFAULT 0 AFTER `price`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `surplus`  smallint(5) UNSIGNED NOT NULL DEFAULT 0 AFTER `num`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `msg`  varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `surplus`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `status`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `msg`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `add_time`  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `status`;
ALTER TABLE `sl_redpack_send` MODIFY COLUMN `draw_time`  int(10) UNSIGNED NOT NULL DEFAULT 0 AFTER `add_time`;
ALTER TABLE `sl_redpack_send` DROP COLUMN `ad_id`;

UPDATE sl_redpack_send SET `show` = 0, receive = 'room', `share` = 0, transfer = 'account';

INSERT INTO `sl_redpack_auto` (`user_id`, `room_id`, `auto`, `mb`, `num`, `receive`, `share`, `pic`, `msg`, `edit_time`)
SELECT user_id,room_id,1,100,10,'room',0,'','',1537545600 FROM sl_challenge_room ;

-- 2.3.0
CREATE TABLE `sl_goods_banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `img` varchar(100) NOT NULL,
  `link` varchar(100) NOT NULL,
  `sort` tinyint(3) unsigned NOT NULL,
  `status` char(1) NOT NULL DEFAULT '1',
  `add_time` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 2.3.2
ALTER TABLE `sl_challenge_room` ADD COLUMN `auto`  char(1) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT '1' AFTER `mode`;

-- 2.4.0
CREATE TABLE `sl_active` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(20) NOT NULL,
  `title` varchar(20) NOT NULL,
  `begin_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  `link` varchar(100) NOT NULL,
  `is_login` char(1) NOT NULL DEFAULT '1',
  `add_time` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `sl_active` (`id`, `name`, `title`, `begin_time`, `end_time`, `link`, `is_login`, `add_time`) VALUES ('1', 'pull_new', '拉新活动', '1540051200', '1543075200', '/activity/pull_new/index.html', '1', '1540282203');


CREATE TABLE `sl_active_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单id',
  `is_partner` tinyint(1) NOT NULL COMMENT '是否合伙人',
  `invite_user_id` int(11) NOT NULL,
  `add_time` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL COMMENT '订单状态',
  `user_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `sl_activity_pull_new` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned NOT NULL,
  `num` int(11) unsigned NOT NULL,
  `edit_time` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


