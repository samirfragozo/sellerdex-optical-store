function readCookie(name: string): string | null {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));

    return match ? decodeURIComponent(match[1]) : null;
}

/** A same-origin fetch wrapper that sends the Laravel XSRF cookie as a header — the project has no axios instance to do this automatically. */
export function csrfFetch(url: string, options: RequestInit = {}): Promise<Response> {
    const token = readCookie('XSRF-TOKEN');

    return fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            ...options.headers,
        },
    });
}
