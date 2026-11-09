<?php
namespace App\Http\Middleware;
use App\Services\PlainTextInput;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
// Group the reject unsafe input actions.
class RejectUnsafeInput
{
    // Run the reject unsafe input checks or action.
    public function handle(Request $request, Closure $next)
    {
        $errors=[];
        $walk=function(array $values,string $prefix='') use (&$walk,&$errors) {
            // Process each value.
            foreach($values as $key=>$value){
                // Skip this item if the key check matches.
                if(preg_match('/password|^_token$|^reset_token$|^otp$/i',(string)$key))continue;
                $name=$prefix===''?(string)$key:$prefix.'.'.$key;
                // Run this step if the value is a list.
                if(is_array($value)){$walk($value,$name);continue;}
                // Run this step if the value checks match.
                if(is_string($value) && PlainTextInput::invalid($value))$errors[$name]='Invalid input. Enter plain text without angle brackets, markup, or executable code.';
                // Run this step if the value and get client original name checks match.
                if($value instanceof \Illuminate\Http\UploadedFile && PlainTextInput::invalid($value->getClientOriginalName()))$errors[$name]='Invalid file name. Rename the file without markup or code.';
            }
        };
        $walk($request->all());
        // Stop if the errors is set.
        if($errors)throw ValidationException::withMessages($errors);
        return $next($request);
    }
}
