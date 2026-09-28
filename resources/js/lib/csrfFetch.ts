function readCookie(name: string): string | null {
    const match = document.cookie.match(
        new RegExp('(?:^|; )' + name + '=([^;]*)'),
    );

    return match ? decodeURIComponent(match[1]) : null;
}

/** A same-origin fetch wrapper that sends the Laravel XSRF cookie as a header — the project has no axios instance to do this automatically. */
export function csrfFetch(
    url: string,
    options: RequestInit = {},
): Promise<Response> {
    const token = readCookie('XSRF-TOKEN');
    // A FormData body (e.g. a file upload) needs the browser to set its own
    // multipart boundary, and URLSearchParams gets its own urlencoded
    // Content-Type too — forcing application/json here would break both.
    const setsOwnContentType =
        options.body instanceof FormData ||
        options.body instanceof URLSearchParams;

    return fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(setsOwnContentType
                ? {}
                : { 'Content-Type': 'application/json' }),
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            ...options.headers,
        },
    });
}
