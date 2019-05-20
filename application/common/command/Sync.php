<?php

namespace app\common\command;

use app\common\model\Activity;
use app\common\model\Challenge;
use app\common\model\Redpack;
use app\common\model\User;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use app\common\model\Sync as SyncModel;

class Sync extends Command
{
    protected function configure()
    {
        $this->setName('sync')
            ->addArgument('name', Argument::OPTIONAL, "task name")
            //->addOption('city', null, Option::VALUE_REQUIRED, 'city name')
            ->setDescription('Say Hello');
    }

    protected function execute(Input $input, Output $output)
    {
        $name = trim($input->getArgument('name'));
        $Sync = new SyncModel();

        switch ($name) {
            case 'round_1m': // 1分钟执行任务
                $Sync->challenge_auto_launch(); // 自动发起
                $Sync->challenge_auto_join(); // 自动加入
                Challenge::auto_accept(); // 自动接受
                break;
            case 'round_10m': // 10分钟执行任务
                // Activity::pull_new_system();
                Activity::pick_new_cash_auto();
                User::xinge_push(); // 信鸽推送
                // $Sync->no_superior_dynamic(); // 无上级用户红包推送
                break;
            case 'time_0000': // 0点执行
                Activity::pull_new_finish();
                break;
            case 'time_0100': // 1点执行
                Challenge::rollback(); // 退回未接挑战
                Challenge::room_expire_dynamic(); // 族群续费提醒
                break;
            case 'time_0500': // 5点执行
                Redpack::auto_send_set(); // 族长自动发红包
                Redpack::auto_send_default(); // 系统自动发红包
                break;
            case 'time_0900': // 9点执行
                Challenge::signin_system_user(); // 系统用户打卡
                break;
            case 'time_1100': // 11点执行
                Challenge::stat(); // 结算
                break;
            case 'time_1200': // 12点执行
                User::stat_mb_change(); // 手动结算M币
                break;
            case 'day_init':
                // 抓取商品
                $Sync->get_goods();
                $Sync->get_jd_goods();
                // 每日统计
                $Sync->promotion_day_stat();
                $Sync->promotion_day_stat_jd();
                break;
            case 'week_init':
                $Sync->week_stat();
                break;
            // 00  02  *  *  * 抓取商品
            case 'get_goods':
                $Sync->get_goods();
                $Sync->po_buy_one_activity_goods();
                break;
            // *  */1  *  *  * 抓取拼多多订单
            case 'get_ddk_order':
                $Sync->get_ddk_order();
                break;
            // 00  00  *  *  * 每日统计
            case 'day_stat':
                $Sync->promotion_day_stat();
                break;
            // 月统计
            case 'promotion_stat':
                $Sync->promotion_stat();
                break;
            case 'auto_launch':
                $Sync->challenge_auto_launch();
                break;
            case 'auto_accept':
                Challenge::auto_accept();
                break;
            case 'auto_join':
                $Sync->challenge_auto_join();
                break;
            case 'stat':
                Challenge::stat();
                break;
            case 'stat_room_info':
                Challenge::stat_room_info();
                break;
            case 'stat_record':
                Challenge::stat_record();
                break;
            case 'stat_record_both':
                Challenge::stat_record_both();
                break;
            case 'stat_record_room':
                Challenge::stat_record_room();
                break;
            case 'challenge_rollback':
                Challenge::rollback();
                break;
            case 'room_expire_dynamic':
                Challenge::room_expire_dynamic();
                break;
            case 'stat_mb_record':
                User::stat_mb_record();
                break;
            case 'stat_mb_auto':
                User::stat_mb_auto();
                break;
            case 'stat_mb_change':
                User::stat_mb_change();
                break;
            case 'signin_system_user':
                Challenge::signin_system_user();
                break;
            case 'xinge_push':
                User::xinge_push();
                break;
            case 'fake_order_add':
                $Sync->order_add();
                break;
            case 'fake_partner_add':
                $Sync->partner_add();
                break;
            case 'redpack_auto_send_set':
                Redpack::auto_send_set();
                break;
            case 'redpack_auto_send_default':
                Redpack::auto_send_default();
                break;
            // 00  02  *  *  * 抓取京东商品
            case 'get_jd_goods':
                $Sync->get_jd_goods();
                break;
            case 'get_jd_order':
                $Sync->get_jd_order();
                break;
            // 00  00  *  *  * 每日统计
            case 'promotion_day_stat_jd':
                $Sync->promotion_day_stat_jd();
                break;
            // 月统计
            case 'promotion_stat_jd':
                $Sync->promotion_stat_jd();
                break;
            case 'auto_room_fee':
                Challenge::auto_room_fee();
                break;
            case 'pull_new_finish':
                Activity::pull_new_finish();
                break;
            case 'pull_new_system':
                Activity::pull_new_system();
                break;
            case 'level_promote_commission_grant':
                $Sync->level_promote_commission_grant();
                break;
            case 'pick_new_cash_auto':
                Activity::pick_new_cash_auto();
                break;
            case 'no_superior_dynamic':
                $Sync->no_superior_dynamic();
                break;
            case 'po_buy_one_activity_goods':
                $Sync->po_buy_one_activity_goods();
                break;
            default:
                $output->writeln("no task");
                break;
        }

        $output->writeln("end task");
    }
}