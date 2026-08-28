<?php

namespace Database\Seeders;

use App\Models\Etp;
use Illuminate\Database\Seeder;

class EtpSeeder extends Seeder
{
    public function run(): void
    {
        $etps = [
            [
                'code' => 'ETP_EETP',
                'name' => 'АКЦИОНЕРНОЕ ОБЩЕСТВО «ЕДИНАЯ ЭЛЕКТРОННАЯ ТОРГОВАЯ ПЛОЩАДКА»',
                'published' => true,
                'site' => 'ROSELTORG.RU',
                'short_name' => 'АО «ЕЭТП»',
                'icon_url' => '61bb79f05925817b31629a6f',
                'icon_file_name' => 'площадка=roseltorg, размер=80.png',
                'key_etp' => 'f18bf112038c386bfbd5359152b1618137f1bc60',
                'yg_sticker_id' => 'd0a83fa7afbc',
                'order' => 1,
            ],
            [
                'code' => 'ETP_RTS',
                'name' => 'ОБЩЕСТВО С ОГРАНИЧЕННОЙ ОТВЕТСТВЕННОСТЬЮ «РТС-ТЕНДЕР»',
                'published' => true,
                'site' => 'RTS-TENDER.RU',
                'short_name' => 'РТС-тендер',
                'icon_url' => '61bb79f15925817b31629ac1',
                'icon_file_name' => 'площадка=rst, размер=80.png',
                'key_etp' => 'ba769d1ee1256a5fbcc65b17b89de9dfb5673c20',
                'yg_sticker_id' => 'f528c134f75b',
                'order' => 2,
            ],
            [
                'code' => 'ETP_SBAST',
                'name' => 'АКЦИОНЕРНОЕ ОБЩЕСТВО «СБЕРБАНК - АВТОМАТИЗИРОВАННАЯ СИСТЕМА ТОРГОВ»',
                'published' => true,
                'site' => 'SBERBANK-AST.RU',
                'short_name' => 'АО «Сбербанк-АСТ»',
                'icon_url' => '61bb79f05925817b31629aaf',
                'icon_file_name' => 'площадка=Sber, размер=80.png',
                'key_etp' => '42680edbc63951e2f0267d9a3a03a2e057053ae4',
                'yg_sticker_id' => 'b81678670cb2',
                'order' => 3,
            ],
            [
                'code' => 'ETP_AVK',
                'name' => 'АКЦИОНЕРНОЕ ОБЩЕСТВО «АГЕНТСТВО ПО ГОСУДАРСТВЕННОМУ ЗАКАЗУ РЕСПУБЛИКИ ТАТАРСТАН»',
                'published' => true,
                'site' => 'ETP.ZAKAZRF.RU',
                'short_name' => 'АГЗ РТ',
                'icon_url' => '61bb79f05925817b31629a5f',
                'icon_file_name' => 'площадка=oset, размер=80.png',
                'key_etp' => '3f64b6582a43d13fc4b577cba0d36081898a9c09',
                'yg_sticker_id' => '09a2a0c30128',
                'order' => 4,
            ],
            [
                'code' => 'ETP_TEKTORG',
                'name' => 'АКЦИОНЕРНОЕ ОБЩЕСТВО «ТЭК-Торг»',
                'published' => true,
                'site' => 'TEKTORG.RU',
                'short_name' => 'ЭТП ТЭК-Торг',
                'icon_url' => '61bb79f05925817b31629ab9',
                'icon_file_name' => 'площадка=tektorg, размер=80.png',
                'yg_sticker_id' => 'f53c4ae81132',
                'order' => 5,
            ],
            [
                'code' => 'ETP_GPB',
                'name' => 'ООО «ЭЛЕКТРОННАЯ ТОРГОВАЯ ПЛОЩАДКА ГПБ»',
                'published' => true,
                'site' => 'ETPGPB.RU/products/sales/',
                'short_name' => 'ЭТП Газпромбанк',
                'icon_url' => '61bb79f05925817b31629acb',
                'icon_file_name' => 'площадка=etpGpb, размер=80.png',
                'key_etp' => '938a6b04e5e58e8cfa3990f50a7b0f80467766b1',
                'yg_sticker_id' => '41c630d27bef',
                'order' => 6,
            ],
            [
                'code' => 'ETP_MMVB',
                'name' => 'ЭЛЕКТРОННАЯ ТОРГОВАЯ ПЛОЩАДКА «ФАБРИКАНТ»',
                'published' => true,
                'site' => 'FABRIKANT.RU',
                'short_name' => 'ЭТП «Фабрикант»',
                'icon_url' => '6465f956d9ef802c646ae95e',
                'icon_file_name' => 'Fabrikant 80х80_лазурный_круг(52х52).svg',
                'key_etp' => '493b17ed32d802bf96d87f0f531010b7bcd86754',
                'yg_sticker_id' => '78d2819d041c',
                'order' => 7,
            ],
            [
                'code' => 'ETP_RAD',
                'name' => 'АКЦИОНЕРНОЕ ОБЩЕСТВО «РОССИЙСКИЙ АУКЦИОННЫЙ ДОМ»',
                'published' => true,
                'site' => 'CATALOG.LOT-ONLINE.RU',
                'short_name' => 'АО «РАД»',
                'icon_url' => '61cdccf479a4450c49d9683b',
                'icon_file_name' => 'площадка=rad, размер=80 (1).png',
                'key_etp' => '057a402253d66e5b36bc4fb6f781549b0e2db2c2',
                'yg_sticker_id' => 'a256c9600c7c',
                'order' => 8,
            ],
        ];

        foreach ($etps as $etp) {
            Etp::updateOrCreate(['code' => $etp['code']], $etp);
        }
    }
}
