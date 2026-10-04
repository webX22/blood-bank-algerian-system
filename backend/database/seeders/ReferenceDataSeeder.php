<?php

namespace Database\Seeders;

use App\Models\BloodComponent;
use App\Models\BloodType;
use App\Models\Wilaya;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['A+', 'A positive'], ['A-', 'A negative'],
            ['B+', 'B positive'], ['B-', 'B negative'],
            ['AB+', 'AB positive'], ['AB-', 'AB negative'],
            ['O+', 'O positive'], ['O-', 'O negative'],
        ] as [$code, $name]) {
            BloodType::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ([
            ['RBC', 'Red blood cells'],
            ['PLASMA', 'Plasma'],
            ['PLATELETS', 'Platelets'],
        ] as [$code, $name]) {
            BloodComponent::updateOrCreate(['code' => $code], ['name' => $name]);
        }

        foreach ($this->wilayas() as [$code, $nameAr, $nameFr, $nameEn]) {
            Wilaya::updateOrCreate(
                ['code' => $code],
                [
                    'name_ar' => $nameAr,
                    'name_fr' => $nameFr,
                    'name_en' => $nameEn,
                    'slug' => Str::slug($nameEn),
                ],
            );
        }
    }

    /**
     * Conventional wilaya names for initial navigation; validate against the
     * current national registry before using these labels operationally.
     *
     * @return array<int, array{string, string, string, string}>
     */
    private function wilayas(): array
    {
        return [
            ['01', 'أدرار', 'Adrar', 'Adrar'],
            ['02', 'الشلف', 'Chlef', 'Chlef'],
            ['03', 'الأغواط', 'Laghouat', 'Laghouat'],
            ['04', 'أم البواقي', 'Oum El Bouaghi', 'Oum El Bouaghi'],
            ['05', 'باتنة', 'Batna', 'Batna'],
            ['06', 'بجاية', 'Béjaïa', 'Bejaia'],
            ['07', 'بسكرة', 'Biskra', 'Biskra'],
            ['08', 'بشار', 'Béchar', 'Bechar'],
            ['09', 'البليدة', 'Blida', 'Blida'],
            ['10', 'البويرة', 'Bouira', 'Bouira'],
            ['11', 'تمنراست', 'Tamanrasset', 'Tamanrasset'],
            ['12', 'تبسة', 'Tébessa', 'Tebessa'],
            ['13', 'تلمسان', 'Tlemcen', 'Tlemcen'],
            ['14', 'تيارت', 'Tiaret', 'Tiaret'],
            ['15', 'تيزي وزو', 'Tizi Ouzou', 'Tizi Ouzou'],
            ['16', 'الجزائر', 'Alger', 'Algiers'],
            ['17', 'الجلفة', 'Djelfa', 'Djelfa'],
            ['18', 'جيجل', 'Jijel', 'Jijel'],
            ['19', 'سطيف', 'Sétif', 'Setif'],
            ['20', 'سعيدة', 'Saïda', 'Saida'],
            ['21', 'سكيكدة', 'Skikda', 'Skikda'],
            ['22', 'سيدي بلعباس', 'Sidi Bel Abbès', 'Sidi Bel Abbes'],
            ['23', 'عنابة', 'Annaba', 'Annaba'],
            ['24', 'قالمة', 'Guelma', 'Guelma'],
            ['25', 'قسنطينة', 'Constantine', 'Constantine'],
            ['26', 'المدية', 'Médéa', 'Medea'],
            ['27', 'مستغانم', 'Mostaganem', 'Mostaganem'],
            ['28', 'المسيلة', 'M’Sila', 'M Sila'],
            ['29', 'معسكر', 'Mascara', 'Mascara'],
            ['30', 'ورقلة', 'Ouargla', 'Ouargla'],
            ['31', 'وهران', 'Oran', 'Oran'],
            ['32', 'البيض', 'El Bayadh', 'El Bayadh'],
            ['33', 'إليزي', 'Illizi', 'Illizi'],
            ['34', 'برج بوعريريج', 'Bordj Bou Arréridj', 'Bordj Bou Arreridj'],
            ['35', 'بومرداس', 'Boumerdès', 'Boumerdes'],
            ['36', 'الطارف', 'El Tarf', 'El Tarf'],
            ['37', 'تندوف', 'Tindouf', 'Tindouf'],
            ['38', 'تيسمسيلت', 'Tissemsilt', 'Tissemsilt'],
            ['39', 'الوادي', 'El Oued', 'El Oued'],
            ['40', 'خنشلة', 'Khenchela', 'Khenchela'],
            ['41', 'سوق أهراس', 'Souk Ahras', 'Souk Ahras'],
            ['42', 'تيبازة', 'Tipaza', 'Tipaza'],
            ['43', 'ميلة', 'Mila', 'Mila'],
            ['44', 'عين الدفلى', 'Aïn Defla', 'Ain Defla'],
            ['45', 'النعامة', 'Naâma', 'Naama'],
            ['46', 'عين تموشنت', 'Aïn Témouchent', 'Ain Temouchent'],
            ['47', 'غرداية', 'Ghardaïa', 'Ghardaia'],
            ['48', 'غليزان', 'Relizane', 'Relizane'],
            ['49', 'تيميمون', 'Timimoun', 'Timimoun'],
            ['50', 'برج باجي مختار', 'Bordj Badji Mokhtar', 'Bordj Badji Mokhtar'],
            ['51', 'أولاد جلال', 'Ouled Djellal', 'Ouled Djellal'],
            ['52', 'بني عباس', 'Béni Abbès', 'Beni Abbes'],
            ['53', 'عين صالح', 'In Salah', 'In Salah'],
            ['54', 'عين قزام', 'In Guezzam', 'In Guezzam'],
            ['55', 'تقرت', 'Touggourt', 'Touggourt'],
            ['56', 'جانت', 'Djanet', 'Djanet'],
            ['57', 'المغير', 'El Meghaier', 'El Meghaier'],
            ['58', 'المنيعة', 'El Meniaa', 'El Meniaa'],
        ];
    }
}
