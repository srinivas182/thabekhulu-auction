export type Fact = { label: string; value: string };

export type ListingCardData = {
    ulid: string;
    title: string;
    url: string;
    category: string;
    categorySlug: string;
    city: string | null;
    province: string | null;
    suburb: string | null;
    image: string | null;
    imageAlt: string;
    minimum: number | null;
    status: { key: 'live' | 'closing' | 'soon'; label: string };
    startsAt: string | null;
    endsAt: string | null;
    opensLabel: string | null;
    closesLabel: string | null;
    renovated: boolean;
    featured: boolean;
    badges?: string[];
    auctionType: 'open' | 'sealed';
    facts: Fact[];
    position: { lat: number; lng: number } | null;
};
