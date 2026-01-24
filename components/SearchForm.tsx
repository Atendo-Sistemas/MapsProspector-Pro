import { getProspects } from "../services/prospectService";

const handleSearch = async () => {
  const results = await getProspects(query, location);
  onResults(results);
};
