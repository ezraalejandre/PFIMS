<?php
namespace App\Http\Middleware;
use App\Services\PlainTextInput;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class RejectUnsafeInput
{
    public function handle(Request $request, Closure $next)
    {
        $errors=[];
        $walk=function(array $values,string $prefix='') use (&$walk,&$errors) {
            foreach($values as $key=>$value){
                if(preg_match('/password|^_token$|^reset_token$|^otp$/i',(string)$key))continue;
                $name=$prefix===''?(string)$key:$prefix.'.'.$key;
                if(is_array($value)){$walk($value,$name);continue;}
                if(is_string($value) && PlainTextInput::invalid($value))$errors[$name]='Invalid input. Enter plain text without angle brackets, markup, or executable code.';
                if($value instanceof \Illuminate\Http\UploadedFile && PlainTextInput::invalid($value->getClientOriginalName()))$errors[$name]='Invalid file name. Rename the file without markup or code.';
            }
        };
        $walk($request->all());
        if($errors)throw ValidationException::withMessages($errors);
        return $next($request);
    }
}
