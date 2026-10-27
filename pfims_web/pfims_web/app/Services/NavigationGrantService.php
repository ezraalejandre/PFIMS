<?php
namespace App\Services;
use Illuminate\Http\Request;
class NavigationGrantService {
    public function allow(Request $request,string $target): void {
        $parts=parse_url($target);
        abort_if($parts===false || isset($parts['host']) && !hash_equals($request->getHost(),$parts['host']),422,'Invalid navigation target.');
        $path=$parts['path']??'/';
        abort_if(!str_starts_with($path,'/') || str_starts_with($path,'//') || preg_match('/[\r\n\\\\]/',$path),422,'Invalid navigation target.');
        $target=$path.(isset($parts['query'])?'?'.$parts['query']:'');
        $request->session()->put('navigation_grant',['target'=>$target,'expires'=>now()->addSeconds(15)->timestamp]);
    }
    public function consume(Request $request): bool {
        $grant=$request->session()->pull('navigation_grant');
        return is_array($grant) && ($grant['expires']??0)>=now()->timestamp && hash_equals((string)($grant['target']??''),$request->getRequestUri());
    }
}
