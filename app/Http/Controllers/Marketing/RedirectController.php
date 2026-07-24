<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Redirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectController extends Controller
{
    /**
     * Route fallback: 301 old Homestead Herds URLs to their migrated posts to
     * preserve link equity (§8, §10b). Falls through to a normal 404 when no
     * redirect is recorded for the requested path.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $path = '/'.trim($request->path(), '/');

        $redirect = Redirect::where('from_path', $path)->first();

        abort_if($redirect === null, 404);

        $target = $redirect->to_url
            ?: ($redirect->post ? "/blog/{$redirect->post->slug}" : '/');

        return redirect($target, $redirect->status);
    }
}
