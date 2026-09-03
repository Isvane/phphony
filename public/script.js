document.addEventListener('DOMContentLoaded', () => {
    const codeBlock = document.querySelector('pre');
    if (codeBlock) {
        const copyBtn = document.createElement('button');
        copyBtn.innerText = 'Copy';
        copyBtn.style.cssText = `
            float: right;
            background: var(--border);
            color: var(--heading);
            border: none;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.75rem;
        `;

        copyBtn.addEventListener('click', () => {
            const textToCopy = codeBlock.querySelector('code').innerText;
            navigator.clipboard.writeText(textToCopy).then(() => {
                copyBtn.innerText = 'Copied!';
                setTimeout(() => copyBtn.innerText = 'Copy', 2000);
            });
        });

        codeBlock.prepend(copyBtn);
    }

    const list = document.querySelector('ul');
    if (list) {
        const timeLi = document.createElement('li');
        const updateTime = () => {
            const options = { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', second: '2-digit' };
            const currentTime = new Intl.DateTimeFormat([], options).format(new Date());
            timeLi.innerHTML = `<strong>Local Time:</strong> ${currentTime} WIB`;
        };
        updateTime();
        setInterval(updateTime, 1000);
        list.appendChild(timeLi);
    }
});
