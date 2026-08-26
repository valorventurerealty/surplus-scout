<?php

namespace App\Services\SalesCopilot;

use Illuminate\Support\Str;

class SalesCopilotClassifier
{
    /** @return array{type:string,confidence:float,resistance:string,legal:bool,dnc:bool} */
    public function classify(string $statement): array
    {
        $text = Str::lower(Str::ascii($statement));
        if ($this->contains($text, ['stop calling','do not call','dont call','remove me','do not contact','dont contact','leave me alone','no more calls'])) return ['type'=>'do_not_contact','confidence'=>1.0,'resistance'=>'hostile','legal'=>false,'dnc'=>true];
        if ($this->contains($text, ['what statute','florida statute','legal advice','legally entitled','probate law','what law','my attorney said'])) return ['type'=>'legal_question','confidence'=>.99,'resistance'=>'guarded','legal'=>true,'dnc'=>false];
        $map = [
            'price_fee_concern'=>['12%','12 percent','fee','percentage','too expensive','cost too much'],
            'legitimacy_concern'=>['scam','legit','legitimate','fraud','trust your company'],
            'diy_concern'=>['do it myself','handle it myself','file it myself','dont need help','do not need help'],
            'information_request'=>['send me information','send info','email me','mail me information'],
            'decision_maker_concern'=>['talk to my wife','talk to my husband','talk to my spouse','talk to my attorney','talk to my brother','talk to my sister','talk to my partner'],
            'too_good_to_be_true'=>['too good to be true','cant be real','cannot be real'],
            'not_interested'=>['not interested','no interest'],
            'competitor_comparison'=>['different about you','other companies','why your company','what makes you different'],
            'timing_concern'=>['call me later','call me back','get back to you','need to think','not a good time','busy right now'],
        ];
        foreach ($map as $type => $phrases) if ($this->contains($text, $phrases)) return ['type'=>$type,'confidence'=>.96,'resistance'=>in_array($type,['legitimacy_concern','not_interested'],true)?'skeptical':'guarded','legal'=>false,'dnc'=>false];
        return ['type'=>'unknown_needs_clarification','confidence'=>.55,'resistance'=>'neutral','legal'=>false,'dnc'=>false];
    }

    private function contains(string $text, array $phrases): bool
    {
        foreach ($phrases as $phrase) if (str_contains($text, $phrase)) return true;
        return false;
    }
}
