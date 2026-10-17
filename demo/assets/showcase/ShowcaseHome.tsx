type StackItem = {
    label: string;
    value: string;
};

type PackageItem = {
    name: string;
    description: string;
};

export type ShowcaseHomeProps = {
    title: string;
    stack: StackItem[];
    packages: PackageItem[];
};

export default function ShowcaseHome({ title, stack, packages }: ShowcaseHomeProps) {
    return (
        <main className="home">
            <header className="home-header">
                <h1>{title}</h1>
                <p className="home-lead">Demo of the PHP Architecture Kit packages.</p>
                <ul className="stack">
                    {stack.map((item) => (
                        <li key={item.label} className="stack-item">
                            <span className="stack-label">{item.label}</span>
                            <span className="stack-value">{item.value}</span>
                        </li>
                    ))}
                </ul>
            </header>

            <section>
                <h2>Packages</h2>
                {packages.length === 0 ? (
                    <p className="empty">No packages connected yet.</p>
                ) : (
                    <ul className="packages">
                        {packages.map((item) => (
                            <li key={item.name} className="package">
                                <h3>{item.name}</h3>
                                <p>{item.description}</p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </main>
    );
}
